<?php

namespace App\Modules\Reimbursements\Services;

use App\Modules\Users\Models\User;
use App\Modules\Reimbursements\Models\Receipt;
use App\Modules\Reimbursements\Models\Reimbursement;
use App\Modules\AuditLogs\Services\AuditLogService;
use App\Modules\Reimbursements\Jobs\UpdatePrsReimbursementStatusJob;
use App\Modules\Shared\Services\PasswordVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReimbursementService
{
    /**
     * List all reimbursements.
     */
    public function listReimbursements(User $user, bool $canManage)
    {
        if (!$canManage) {
            return Reimbursement::with(['receipts', 'user'])->where('user_id', $user->id)->get();
        }

        return Reimbursement::with(['receipts', 'user'])->get();
    }

    /**
     * Submit a new reimbursement request.
     */
    public function storeReimbursement(User $user, array $validated, $reportFile)
    {
        return DB::transaction(function () use ($user, $validated, $reportFile) {
            if (!empty($validated['receipts'])) {
                foreach ($validated['receipts'] as $receiptData) {
                    $receipt = Receipt::find($receiptData['id']);
                    if ($receipt && $receipt->uploaded_by === $user->id) {
                        $receipt->update([
                            'vendor_name' => $receiptData['vendor_name'] ?? $receipt->vendor_name,
                            'transaction_date' => $receiptData['transaction_date'] ?? $receipt->transaction_date,
                            'total_amount' => $receiptData['total_amount'] ?? $receipt->total_amount,
                            'vat_amount' => $receiptData['vat_amount'] ?? $receipt->vat_amount,
                            'vat_classification' => $receiptData['vat_classification'] ?? $receipt->vat_classification,
                            'tin' => $receiptData['tin'] ?? $receipt->tin,
                            'invoice_number' => $receiptData['invoice_number'] ?? $receipt->invoice_number,
                        ]);

                        if (isset($receiptData['items'])) {
                            $receipt->items()->delete();
                            $receipt->items()->createMany($receiptData['items']);
                        }
                    }
                }
            }

            $reportPath = null;
            if ($reportFile) {
                $reportPath = $reportFile->store('reports', 'supabase');
            }

            $reimbursement = Reimbursement::create([
                'user_id' => $user->id,
                'description' => $validated['description'],
                'category' => $validated['category'],
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'cutoff_period' => $validated['cutoff_period'],
                'report_file_path' => $reportPath,
                'status' => 'pending',
                'submitted_by_name' => $user->name,
            ]);

            $reimbursement->receipts()->attach($validated['receipt_ids']);

            return $reimbursement->load('receipts');
        });
    }

    /**
     * View detailed claim.
     */
    public function showReimbursement(User $user, int $id, bool $canManage)
    {
        $reimbursement = Reimbursement::with(['receipts.items', 'user'])->findOrFail($id);

        if (!$canManage && $reimbursement->user_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Forbidden.');
        }

        return $reimbursement;
    }

    /**
     * Approve claim.
     */
    public function approveReimbursement(User $user, int $id, string $password, string $ipAddress, Request $requestContext)
    {
        return DB::transaction(function () use ($user, $id, $password, $ipAddress, $requestContext) {
            // Verify password against external auth service
            if (!PasswordVerificationService::verify($requestContext, $password)) {
                throw ValidationException::withMessages([
                    'password' => ['Invalid password. Please try again.']
                ]);
            }

            $reimbursement = Reimbursement::findOrFail($id);

            // Self-approval check
            if ($reimbursement->user_id === $user->id) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Conflict. Self-approval is strictly prohibited.');
            }

            $beforeState = $reimbursement->toArray();
            $reimbursement->update(['status' => 'approved']);
            $afterState = $reimbursement->toArray();

            // Immutable Audit Log
            AuditLogService::log(
                actorId: $user->id,
                actorRole: $user->role,
                actionType: 'CLAIM_APPROVED',
                entityType: 'reimbursement',
                entityId: $reimbursement->id,
                beforeState: $beforeState,
                afterState: $afterState,
                ipAddress: $ipAddress
            );

            if ($reimbursement->is_request == 1 && !empty($reimbursement->source_submission_id)) {
                UpdatePrsReimbursementStatusJob::dispatch($reimbursement->source_submission_id);
            }

            return $reimbursement;
        });
    }

    /**
     * Reject claim.
     */
    public function rejectReimbursement(User $user, int $id, string $comment, string $password, string $ipAddress, Request $requestContext)
    {
        return DB::transaction(function () use ($user, $id, $comment, $password, $ipAddress, $requestContext) {
            // Verify password against external auth service
            if (!PasswordVerificationService::verify($requestContext, $password)) {
                throw ValidationException::withMessages([
                    'password' => ['Invalid password. Please try again.']
                ]);
            }

            $reimbursement = Reimbursement::findOrFail($id);

            // Self-rejection check
            if ($reimbursement->user_id === $user->id) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Conflict. Self-rejection/approval is prohibited.');
            }

            $beforeState = $reimbursement->toArray();
            $reimbursement->update([
                'status' => 'rejected',
                'admin_notes' => $comment,
                'rejection_comment' => $comment,
            ]);
            $afterState = $reimbursement->toArray();

            // Immutable Audit Log
            AuditLogService::log(
                actorId: $user->id,
                actorRole: $user->role,
                actionType: 'CLAIM_REJECTED',
                entityType: 'reimbursement',
                entityId: $reimbursement->id,
                beforeState: $beforeState,
                afterState: $afterState,
                ipAddress: $ipAddress
            );

            return $reimbursement;
        });
    }

    /**
     * Update reimbursement details (admin notes, status).
     */
    public function updateReimbursement(User $user, int $id, array $data, bool $canManage)
    {
        if (!$canManage) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized.');
        }

        $reimbursement = Reimbursement::findOrFail($id);
        $reimbursement->update($data);

        return $reimbursement;
    }
}
