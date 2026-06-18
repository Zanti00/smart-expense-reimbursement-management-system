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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReimbursementService
{
    /**
     * List all reimbursements.
     */
    public function listReimbursements(User $user, bool $canManage)
    {
        if (!$canManage) {
            return Reimbursement::with(['receipts.category', 'user', 'expenseCategory'])->where('user_id', $user->id)->get();
        }

        return Reimbursement::with(['receipts.category', 'user', 'expenseCategory'])->get();
    }

    /**
     * Submit a new reimbursement request.
     */
    public function storeReimbursement(User $user, array $validated, $reportFile)
    {
        return DB::transaction(function () use ($user, $validated, $reportFile) {
            $receiptIds = array_values(array_unique($validated['receipt_ids']));
            $ownedReceiptCount = Receipt::whereIn('id', $receiptIds)
                ->where('uploaded_by', $user->id)
                ->count();

            if ($ownedReceiptCount !== count($receiptIds)) {
                throw ValidationException::withMessages([
                    'receipt_ids' => ['You can only submit reimbursements for your own receipts.'],
                ]);
            }

            $claimedReceiptIds = Receipt::whereIn('id', $receiptIds)
                ->whereHas('reimbursements')
                ->pluck('id')
                ->all();

            if (!empty($claimedReceiptIds)) {
                throw ValidationException::withMessages([
                    'receipt_ids' => ['One or more receipts are already attached to a reimbursement.'],
                ]);
            }

            if (!empty($validated['receipts'])) {
                foreach ($validated['receipts'] as $receiptData) {
                    $receipt = Receipt::find($receiptData['id']);
                    if ($receipt && $receipt->uploaded_by === $user->id) {
                        $receipt->update([
                            'expense_category_id' => $receiptData['expense_category_id'] ?? $receipt->expense_category_id,
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

            $expenseCategoryId = $validated['expense_category_id']
                ?? Receipt::whereIn('id', $receiptIds)->whereNotNull('expense_category_id')->value('expense_category_id');

            $reportPath = null;
            if ($reportFile) {
                $reportPath = $reportFile->store('reports', 'supabase');
            }

            $reimbursement = Reimbursement::create([
                'user_id' => $user->id,
                'description' => $validated['description'],
                'expense_category_id' => $expenseCategoryId,
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'cutoff_period' => $validated['cutoff_period'],
                'report_file_path' => $reportPath,
                'status' => 'pending',
                'submitted_by_name' => $user->name,
            ]);

            $reimbursement->receipts()->attach($receiptIds);

            return $reimbursement->load('receipts');
        });
    }

    /**
     * View detailed claim.
     */
    public function showReimbursement(User $user, int $id, bool $canManage)
    {
        $reimbursement = Reimbursement::with(['receipts.items', 'receipts.category', 'user', 'expenseCategory'])->findOrFail($id);

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
     * Update reimbursement details.
     *
     * Supports two modes:
     * 1. Admin: update admin_notes / status (existing behaviour)
     * 2. Employee self-edit: update fields when status is pending or rejected
     */
    public function updateReimbursement(User $user, int $id, array $data, bool $canManage, $reportFile = null)
    {
        $reimbursement = Reimbursement::findOrFail($id);

        // Admin mode — existing behaviour
        if ($canManage && ($user->id !== $reimbursement->user_id)) {
            $reimbursement->update($data);
            return $reimbursement;
        }

        // Employee self-edit mode
        if ($reimbursement->user_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Forbidden. You do not own this reimbursement.');
        }

        if (!in_array($reimbursement->status, ['pending', 'rejected'])) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Only pending or rejected reimbursements can be edited.');
        }

        return DB::transaction(function () use ($reimbursement, $user, $data, $reportFile) {
            // Update receipt data if provided
            if (!empty($data['receipts'])) {
                foreach ($data['receipts'] as $receiptData) {
                    $receipt = Receipt::find($receiptData['id']);
                    if ($receipt && $receipt->uploaded_by === $user->id) {
                        $receipt->update([
                            'expense_category_id' => $receiptData['expense_category_id'] ?? $receipt->expense_category_id,
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

            // Sync receipt associations if provided
            if (!empty($data['receipt_ids'])) {
                $reimbursement->receipts()->sync($data['receipt_ids']);
            }

            // Handle report file upload
            $reportPath = $reimbursement->report_file_path;
            if ($reportFile) {
                // Delete old file if exists
                if ($reportPath) {
                    Storage::disk('supabase')->delete($reportPath);
                }
                $reportPath = $reportFile->store('reports', 'supabase');
            }

            // Build update payload
            $updatePayload = array_filter([
                'description' => $data['description'] ?? null,
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'amount' => $data['amount'] ?? null,
                'date' => $data['date'] ?? null,
                'cutoff_period' => $data['cutoff_period'] ?? null,
                'report_file_path' => $reportPath,
            ], fn($v) => $v !== null);

            // Reset rejected → pending on re-submission
            if ($reimbursement->status === 'rejected') {
                $updatePayload['status'] = 'pending';
                $updatePayload['rejection_comment'] = null;
            }

            $reimbursement->update($updatePayload);

            return $reimbursement->load('receipts');
        });
    }

    /**
     * Delete a pending reimbursement request.
     *
     * Only the owner can delete, and only when status is pending.
     */
    public function deleteReimbursement(User $user, int $id, string $password, Request $requestContext)
    {
        $reimbursement = Reimbursement::findOrFail($id);

        if ($reimbursement->user_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Forbidden. You do not own this reimbursement.');
        }

        if ($reimbursement->status !== 'pending') {
            throw new \Illuminate\Auth\Access\AuthorizationException('Only pending reimbursements can be deleted.');
        }

        // Verify password against external auth service
        if (!PasswordVerificationService::verify($requestContext, $password)) {
            throw ValidationException::withMessages([
                'password' => ['Invalid password. Please try again.']
            ]);
        }

        return DB::transaction(function () use ($reimbursement) {
            // Detach receipt associations
            $reimbursement->receipts()->detach();

            // Remove report file from storage
            if ($reimbursement->report_file_path) {
                Storage::disk('supabase')->delete($reimbursement->report_file_path);
            }

            $reimbursement->delete();

            return true;
        });
    }
}
