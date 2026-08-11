<?php

namespace App\Modules\Reimbursements\Services;

use App\Modules\Users\Models\User;
use App\Modules\Reimbursements\Models\Receipt;
use App\Modules\AuditLogs\Services\AuditLogService;
use App\Modules\Reimbursements\Jobs\DispatchReceiptToAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use App\Modules\Shared\Traits\ValidatesReceiptDuplicates;

class ReceiptService
{
    use ValidatesReceiptDuplicates;
    /**
     * List all receipts for the user.
     */
    public function listReceipts(User $user, bool $canManage, array $filters = [])
    {
        $query = Receipt::with('category', 'uploader', 'items')
            ->withCount('reimbursements');

        if (!$canManage) {
            $query->where('uploaded_by', $user->id);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('vendor_name', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('file_path', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status']) && strtolower((string) $filters['status']) !== 'all') {
            $query->where('status', strtolower((string) $filters['status']));
        }

        if (!empty($filters['category']) && strtolower((string) $filters['category']) !== 'all') {
            $category = (string) $filters['category'];
            $query->whereHas('category', function ($subQuery) use ($category) {
                $subQuery->where('name', $category);
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = max(1, min($perPage, 10));

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Get a single receipt by ID, ensuring user owns it unless they can manage.
     */
    public function getReceipt(User $user, int $id, bool $canManage)
    {
        $receipt = Receipt::with('category', 'uploader', 'items')
            ->withCount('reimbursements')
            ->findOrFail($id);

        if (!$canManage && $receipt->uploaded_by !== $user->id) {
            throw new AuthorizationException('Unauthorized to view this receipt.');
        }

        return $receipt;
    }

    /**
     * Store a newly uploaded receipt (single or multi-page) in the database.
     */
    public function storeReceipt(User $user, array $validated, $file)
    {
        return DB::transaction(function () use ($user, $validated, $file) {
            $files = is_array($file) ? $file : [$file];
            $storedFile = $this->storeReceiptFiles($files);

            $this->validateDuplicateReceipt($storedFile['file_hash']);

            $receipt = Receipt::create([
                'uploaded_by'          => $user->id,
                'file_path'            => $storedFile['file_path'],
                'file_hash'            => $storedFile['file_hash'],
                'file_type'            => $storedFile['file_type'],
                'file_size_bytes'      => $storedFile['file_size_bytes'],
                'expense_category_id'  => $validated['expense_category_id'] ?? null,
                'vendor_name'          => $validated['vendor_name'] ?? null,
                'transaction_date'     => $validated['transaction_date'] ?? null,
                'total_amount'         => $validated['total_amount'] ?? null,
                'vat_amount'           => $validated['vat_amount'] ?? null,
                'tin'                  => $validated['tin'] ?? null,
                'invoice_number'       => $validated['invoice_number'] ?? null,
                'vat_classification'   => $validated['vat_classification'] ?? null,
                'currency'             => $validated['currency'] ?? null,
                'location'             => $validated['location'] ?? null,
                'ocr_flagged'          => false,
                'is_archived'          => false,
                'status'               => 'processing',
            ]);

            if (!empty($validated['items'])) {
                $receipt->items()->createMany($validated['items']);
            }

            // Dispatch to the external AI OCR + categorization service.
            DispatchReceiptToAiService::dispatch($receipt);

            $receipt->load('category', 'items', 'uploader');

            // Audit Log
            AuditLogService::log(
                actorId: $user->id,
                actorRole: $user->role,
                actionType: 'RECEIPT_CREATED',
                entityType: 'receipt',
                entityId: $receipt->id,
                beforeState: null,
                afterState: $receipt->toArray(),
                ipAddress: request()->ip(),
            );

            return $receipt;
        });
    }

    /**
     * Update receipt (admin notes, status).
     */
    public function updateReceipt(User $user, int $id, array $data, bool $canManage)
    {
        if (!$canManage) {
            throw new AuthorizationException('Unauthorized.');
        }

        $receipt = Receipt::findOrFail($id);
        $receipt->update($data);

        return $receipt;
    }

    /**
     * Let the uploader edit a processed receipt while keeping it processed.
     */
    public function resubmitReceipt(User $user, int $id, array $data, $file = null)
    {
        return DB::transaction(function () use ($user, $id, $data, $file) {
            $receipt = Receipt::with('items')->findOrFail($id);

            if ($receipt->uploaded_by !== $user->id) {
                throw new AuthorizationException('Unauthorized. You can only resubmit your own receipts.');
            }

            if ($receipt->status !== 'processed') {
                throw ValidationException::withMessages([
                    'status' => ['Only receipts with processed status can be edited.'],
                ]);
            }

            $updateData = collect($data)
                ->except(['file', 'items'])
                ->toArray();

            if (array_key_exists('vat_classification', $updateData)) {
                $updateData['vat_classification'] = strtolower((string)$updateData['vat_classification']);
            }

            if ($file) {
                $newFile = $this->storeReceiptFiles([$file]);
                if ($newFile['file_hash'] !== $receipt->file_hash) {
                    $this->validateDuplicateReceipt($newFile['file_hash']);
                }
                $updateData = array_merge($updateData, $newFile);
            }

            $updateData['status'] = 'processed';
            $updateData['ocr_flagged'] = false;

            $receipt->update($updateData);

            if (array_key_exists('items', $data)) {
                $receipt->items()->delete();

                if (!empty($data['items'])) {
                    $receipt->items()->createMany($data['items']);
                }
            }

            return $receipt->fresh(['category', 'items', 'uploader'])
                ->loadCount('reimbursements');
        });
    }

    /**
     * Store uploaded files on the configured Supabase disk and return DB column arrays.
     */
    private function storeReceiptFiles(array $files): array
    {
        $filePaths = [];
        $fileHashes = [];
        $fileTypes = [];
        $fileSizes = [];

        foreach ($files as $file) {
            if (!$file) continue;

            $fileType = $file->extension();
            if ($fileType === 'jpg') {
                $fileType = 'jpeg';
            }

            $filePaths[] = $file->store('receipts', 'supabase');
            $fileHashes[] = hash_file('sha256', $file->getRealPath());
            $fileTypes[] = $fileType;
            $fileSizes[] = $file->getSize();
        }

        return [
            'file_path'       => $filePaths,
            'file_hash'       => $fileHashes,
            'file_type'       => $fileTypes,
            'file_size_bytes' => $fileSizes,
        ];
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
                throw new AuthorizationException('Unauthorized. You can only delete your own receipts.');
            }

            if (!$canManage && !in_array($receipt->status, ['processed', 'rejected'])) {
                throw ValidationException::withMessages([
                    'receipt' => ['Only processed or rejected receipts can be deleted.']
                ]);
            }

            // Check constraints: Block deletion if linked to a Reimbursement
            if ($receipt->reimbursements()->exists()) {
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
