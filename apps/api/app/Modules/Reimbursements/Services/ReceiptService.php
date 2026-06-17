<?php

namespace App\Modules\Reimbursements\Services;

use App\Modules\Users\Models\User;
use App\Modules\Reimbursements\Models\Receipt;
use App\Modules\Reimbursements\Models\Reimbursement;
use App\Modules\AuditLogs\Services\AuditLogService;
use App\Modules\Reimbursements\Jobs\ProcessReceiptOcr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiptService
{
    /**
     * List all receipts for the user.
     */
    public function listReceipts(User $user, bool $canManage)
    {
        $query = Receipt::with('category', 'uploader', 'items');

        if (!$canManage) {
            $query->where('uploaded_by', $user->id);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Store a newly uploaded receipt in the database.
     */
    public function storeReceipt(User $user, array $validated, $file)
    {
        return DB::transaction(function () use ($user, $validated, $file) {
            $path = null;
            $fileHash = null;
            $fileType = null;
            $fileSize = null;

            if ($file) {
                // Store in Supabase bucket
                $path = $file->store('receipts', 'supabase');
                $fileHash = hash_file('sha256', $file->getRealPath());
                
                $fileType = $file->extension();
                if ($fileType === 'jpg') {
                    $fileType = 'jpeg';
                }
                $fileSize = $file->getSize();
            }

            $receipt = Receipt::create([
                'uploaded_by' => $user->id,
                'file_path' => $path,
                'file_hash' => $fileHash,
                'file_type' => $fileType,
                'file_size_bytes' => $fileSize,
                'expense_category_id' => $validated['expense_category_id'] ?? null,
                
                // Static mock data since OCR is disabled
                'vendor_name' => $validated['vendor_name'] ?? 'Mock Vendor Corp',
                'transaction_date' => $validated['transaction_date'] ?? now()->toDateString(),
                'total_amount' => $validated['total_amount'] ?? 1500.00,
                'vat_amount' => $validated['vat_amount'] ?? 180.00,
                'tin' => $validated['tin'] ?? '123-456-789-000',
                'invoice_number' => $validated['invoice_number'] ?? 'INV-' . rand(1000, 9999),
                'vat_classification' => $validated['vat_classification'] ?? 'VAT',
                'ocr_confidence_score' => 0.95,
                
                'ocr_flagged' => false,
                'is_archived' => false,
                'status' => 'pending', // Skipped 'processing' queue
            ]);

            if (!empty($validated['items'])) {
                $receipt->items()->createMany($validated['items']);
            }

            // Queue disabled temporarily: ProcessReceiptOcr::dispatch($receipt);

            $receipt->load('category', 'items', 'uploader');

            return $receipt;
        });
    }

    /**
     * Update receipt (admin notes, status).
     */
    public function updateReceipt(User $user, int $id, array $data, bool $canManage)
    {
        if (!$canManage) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized.');
        }

        $receipt = Receipt::findOrFail($id);
        $receipt->update($data);

        return $receipt;
    }

    /**
     * Delete a receipt.
     */
    public function deleteReceipt(User $user, int $id, bool $canManage, string $ipAddress)
    {
        return DB::transaction(function () use ($user, $id, $canManage, $ipAddress) {
            $receipt = Receipt::findOrFail($id);

            // Check RBAC: Only uploader or admin can delete
            if ($receipt->uploaded_by !== $user->id && !$canManage) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized. You can only delete your own receipts.');
            }

            // Check constraints: Block deletion if linked to a Reimbursement
            if (Reimbursement::where('receipt_id', $receipt->id)->exists()) {
                throw ValidationException::withMessages([
                    'receipt' => ['Cannot delete a receipt that is attached to a reimbursement.']
                ]);
            }

            $beforeState = $receipt->toArray();
            
            // Soft delete the receipt
            $receipt->delete();

            // Audit Log
            AuditLogService::log(
                actorId: $user->id,
                actorRole: $user->role,
                actionType: 'RECEIPT_DELETED',
                entityType: 'receipt',
                entityId: $receipt->id,
                beforeState: $beforeState,
                afterState: ['deleted_at' => now()->toDateTimeString()],
                ipAddress: $ipAddress
            );
        });
    }
}
