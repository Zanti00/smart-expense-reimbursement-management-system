<?php

namespace App\Modules\Reimbursements\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reimbursements\Models\Receipt;
use App\Modules\Reimbursements\Models\Reimbursement;
use App\Modules\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PrsWebhookController extends Controller
{
    private const EVENT = 'prs.reimbursement.requested';

    public function __invoke(Request $request): JsonResponse
    {
        $signatureError = $this->signatureError($request);
        if ($signatureError) {
            return response()->json(['message' => $signatureError], 401);
        }

        $payload = $request->json()->all();
        $validator = Validator::make($payload, [
            'event' => ['required', Rule::in([self::EVENT])],
            'source_system' => ['required', Rule::in(['prs'])],
            'source_submission_id' => ['required'],
            'source_user.id' => ['nullable'],
            'source_user.name' => ['nullable', 'string', 'max:255'],
            'source_user.email' => ['nullable', 'email', 'max:255'],
            'source_user.department' => ['nullable', 'string', 'max:255'],
            'receipt.invoice_number' => ['required', 'string', 'max:255'],
            'receipt.transaction_date' => ['required', 'date'],
            'receipt.tin' => ['required', 'string', 'max:255'],
            'receipt.vendor_name' => ['required', 'string', 'max:255'],
            'receipt.expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'receipt.vat_classification' => ['required', Rule::in(['vat', 'non-vat'])],
            'receipt.total_amount' => ['required', 'numeric', 'min:0.01'],
            'receipt.vat_amount' => ['nullable', 'numeric', 'min:0'],
            'receipt.file_url' => ['nullable', 'url', 'max:2048'],
            'receipt.items' => ['nullable', 'array'],
            'receipt.items.*.name' => ['required_with:receipt.items', 'string', 'max:255'],
            'receipt.items.*.quantity' => ['required_with:receipt.items', 'integer', 'min:1'],
            'receipt.items.*.price' => ['required_with:receipt.items', 'numeric', 'gt:0'],
            'reimbursement.description' => ['required', 'string', 'max:255'],
            'reimbursement.category' => ['required', 'string', 'max:100'],
            'reimbursement.amount' => ['required', 'numeric', 'min:0.01'],
            'reimbursement.date' => ['required', 'date'],
            'reimbursement.cutoff_period' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $deliveryId = $request->header('X-PRS-Delivery-Id');
        $existing = Reimbursement::with('receipts')
            ->where('source_system', 'prs')
            ->where('source_submission_id', (string) $payload['source_submission_id'])
            ->first();

        if (!$existing && $deliveryId) {
            $existing = Reimbursement::with('receipts')->where('source_delivery_id', $deliveryId)->first();
        }

        if ($existing) {
            return response()->json([
                'message' => 'PRS reimbursement request already imported.',
                'data' => $existing,
            ]);
        }

        $reimbursement = DB::transaction(function () use ($payload, $deliveryId) {
            $sourceUser = $payload['source_user'] ?? [];
            $receiptData = $payload['receipt'];
            $claimData = $payload['reimbursement'];

            $user = User::firstOrCreate(
                ['email' => $sourceUser['email'] ?? 'prs-user-' . $payload['source_submission_id'] . '@prs.local'],
                [
                    'name' => $sourceUser['name'] ?? 'PRS User ' . $payload['source_submission_id'],
                    'role' => 'employee',
                    'department' => $sourceUser['department'] ?? 'Sales & Marketing',
                ]
            );

            $receipt = Receipt::create([
                'uploaded_by' => $user->id,
                'file_path' => $receiptData['file_url'] ?? null,
                'file_hash' => null,
                'file_type' => null,
                'file_size_bytes' => null,
                'vendor_name' => $receiptData['vendor_name'],
                'transaction_date' => $receiptData['transaction_date'],
                'total_amount' => $receiptData['total_amount'],
                'vat_amount' => $receiptData['vat_amount'] ?? null,
                'tin' => $receiptData['tin'],
                'invoice_number' => $receiptData['invoice_number'],
                'vat_classification' => $receiptData['vat_classification'],
                'expense_category_id' => $receiptData['expense_category_id'] ?? null,
                'status' => 'submitted',
            ]);

            if (!empty($receiptData['items'])) {
                $receipt->items()->createMany($receiptData['items']);
            }

            $reimbursement = Reimbursement::create([
                'user_id' => $user->id,
                'receipt_id' => $receipt->id,
                'description' => $claimData['description'],
                'category' => $claimData['category'],
                'amount' => $claimData['amount'],
                'date' => $claimData['date'],
                'cutoff_period' => $claimData['cutoff_period'],
                'report_file_path' => null,
                'status' => 'submitted',
                'submitted_by_name' => $user->name,
                'source_system' => 'prs',
                'source_submission_id' => (string) $payload['source_submission_id'],
                'source_delivery_id' => $deliveryId,
            ]);

            $reimbursement->receipts()->attach($receipt->id);

            return $reimbursement->load('receipts.items', 'user');
        });

        return response()->json([
            'message' => 'PRS reimbursement request imported.',
            'data' => $reimbursement,
        ], 201);
    }

    private function signatureError(Request $request): ?string
    {
        $secret = config('services.prs.webhook_secret')
            ?: getenv('PRS_WEBHOOK_SECRET')
            ?: ($_ENV['PRS_WEBHOOK_SECRET'] ?? null)
            ?: ($_SERVER['PRS_WEBHOOK_SECRET'] ?? null);
        if (!$secret) {
            return 'PRS webhook secret is not configured.';
        }

        if ($request->header('X-PRS-Event') !== self::EVENT) {
            return 'Invalid PRS webhook event.';
        }

        $timestamp = $request->header('X-PRS-Timestamp');
        $signature = $request->header('X-PRS-Signature');
        if (!$timestamp || !$signature) {
            return 'Missing PRS webhook signature headers.';
        }

        $tolerance = (int) config('services.prs.webhook_tolerance_seconds', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return 'PRS webhook signature has expired.';
        }

        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);

        return hash_equals($expected, $signature) ? null : 'Invalid PRS webhook signature.';
    }
}
