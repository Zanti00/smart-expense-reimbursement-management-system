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
    private function updateReceiptStatuses(array $receiptIds, string $status): void
    {
        if (empty($receiptIds)) {
            return;
        }

        Receipt::whereIn('id', $receiptIds)->update(['status' => $status]);
    }

    /**
     * List all reimbursements.
     */
    public function listReimbursements(User $user, bool $canManage)
    {
        $query = Reimbursement::with(['receipts.category', 'user', 'expenseCategory'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (!$canManage) {
            $query->where('user_id', $user->id);
        }

        return $query->get();
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
                            'location' => $receiptData['location'] ?? $receipt->location,
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
            $this->updateReceiptStatuses($receiptIds, 'pending');

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
    public function approveReimbursement(User $user, int $id, ?string $password, string $ipAddress, Request $requestContext)
    {
        return DB::transaction(function () use ($user, $id, $password, $ipAddress, $requestContext) {
            // Verify password against external auth service if provided
            if (!empty($password) && !PasswordVerificationService::verify($requestContext, $password)) {
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
            $this->updateReceiptStatuses($reimbursement->receipts()->pluck('receipts.id')->all(), 'approved');
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
     * Reject / Revise claim — 3-strike workflow (2 revises + 1 terminal = 3 total).
     *
     * Admin chooses `revise` or `reject` via dropdown. Both increment `revision_count`
     * and map to status `revise` until threshold (2 revises allowed (<=2 revise, >=3 rejected) — 1st/2nd = revise, 3rd = terminal rejected, 3 total) where system auto-flips to `rejected` (terminal).
     * `rejected` is never set directly; it is system-derived only. 2 revises allowed (<=2 revise, >=3 rejected) — 1st/2nd = revise, 3rd = terminal rejected, 3 total.
     */
    public function rejectReimbursement(User $user, int $id, string $comment, ?string $password, string $ipAddress, Request $requestContext, string $action = 'revise')
    {
        return DB::transaction(function () use ($user, $id, $comment, $password, $ipAddress, $requestContext, $action) {
            // Verify password against external auth service if provided
            if (!empty($password) && !PasswordVerificationService::verify($requestContext, $password)) {
                throw ValidationException::withMessages([
                    'password' => ['Invalid password. Please try again.']
                ]);
            }

            $reimbursement = Reimbursement::findOrFail($id);

            // Self-rejection check
            if ($reimbursement->user_id === $user->id) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Conflict. Self-rejection/approval is prohibited.');
            }

            // Only pending or revise can be returned; approved/granted/rejected are terminal for this action
            if (!in_array($reimbursement->status, ['pending', 'revise'])) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Only pending or revise reimbursements can be revised/rejected.');
            }

            $beforeState = $reimbursement->toArray();

            $currentCount = (int) ($reimbursement->revision_count ?? 0);
            $newCount = $currentCount + 1;
            // 2 revises allowed (<=2 revise, >=3 rejected) — 1st/2nd = revise, 3rd = terminal rejected, 3 total
            $newStatus = $newCount <= 2 ? 'revise' : 'rejected';
            $isTerminal = $newStatus === 'rejected';

            $reimbursement->update([
                'status' => $newStatus,
                'revision_count' => $newCount,
                'admin_notes' => $comment,
                'rejection_comment' => $comment,
            ]);
            $this->updateReceiptStatuses($reimbursement->receipts()->pluck('receipts.id')->all(), $newStatus);
            $afterState = $reimbursement->toArray();

            // Immutable Audit Log — distinguish revise vs terminal rejected
            $actionType = $isTerminal ? 'CLAIM_REJECTED' : ($action === 'revise' ? 'CLAIM_REVISED' : 'CLAIM_REJECTED');

            AuditLogService::log(
                actorId: $user->id,
                actorRole: $user->role,
                actionType: $actionType,
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
     * Backwards-compatible alias for revise flow.
     */
    public function reviseReimbursement(User $user, int $id, string $comment, ?string $password, string $ipAddress, Request $requestContext)
    {
        return $this->rejectReimbursement($user, $id, $comment, $password, $ipAddress, $requestContext, 'revise');
    }

    /**
     * Grant (disburse) a previously approved claim.
     */
    public function grantReimbursement(User $user, int $id, ?string $password, string $ipAddress, Request $requestContext)
    {
        return DB::transaction(function () use ($user, $id, $password, $ipAddress, $requestContext) {
            // Verify password against external auth service if provided
            if (!empty($password) && !PasswordVerificationService::verify($requestContext, $password)) {
                throw ValidationException::withMessages([
                    'password' => ['Invalid password. Please try again.']
                ]);
            }

            $reimbursement = Reimbursement::findOrFail($id);

            // Status guard: only approved reimbursements can be granted
            if ($reimbursement->status !== 'approved') {
                throw new \Illuminate\Auth\Access\AuthorizationException('Only approved reimbursements can be granted.');
            }

            // Self-grant check
            if ($reimbursement->user_id === $user->id) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Self-grant is prohibited.');
            }

            $beforeState = $reimbursement->toArray();
            $reimbursement->update(['status' => 'granted']);
            $this->updateReceiptStatuses($reimbursement->receipts()->pluck('receipts.id')->all(), 'granted');
            $afterState = $reimbursement->toArray();

            // Immutable Audit Log
            AuditLogService::log(
                actorId: $user->id,
                actorRole: $user->role,
                actionType: 'CLAIM_GRANTED',
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
     * Update reimbursement details.
     *
     * Supports two modes:
     * 1. Admin: update admin_notes / status (existing behaviour)
     * 2. Employee self-edit: update fields when status is pending or revise
     */
    public function updateReimbursement(User $user, int $id, array $data, bool $canManage, $reportFile = null)
    {
        $reimbursement = Reimbursement::findOrFail($id);

        // Admin mode — existing behaviour
        if ($canManage && ($user->id !== $reimbursement->user_id)) {
            if (!empty($data['status']) && $data['status'] === 'granted') {
                throw new \Illuminate\Auth\Access\AuthorizationException('Use POST /{id}/grant with password verification.');
            }
            $reimbursement->update($data);
            if (!empty($data['status']) && in_array($data['status'], ['pending', 'approved', 'revise', 'rejected'])) {
                $this->updateReceiptStatuses(
                    $reimbursement->receipts()->pluck('receipts.id')->all(),
                    $data['status'],
                );
            }
            return $reimbursement;
        }

         // Employee self-edit mode
        if ($reimbursement->user_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Forbidden. You do not own this reimbursement.');
        }

        if ($reimbursement->status === 'rejected') {
            throw new \Illuminate\Auth\Access\AuthorizationException('Rejected reimbursements (exceeded revision limit) cannot be edited.');
        }

        if (!in_array($reimbursement->status, ['pending', 'revise'])) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Only pending or revise reimbursements can be edited.');
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
                            'location' => $receiptData['location'] ?? $receipt->location,
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
                $currentReceiptIds = $reimbursement->receipts()->pluck('receipts.id')->all();
                $newReceiptIds = array_values(array_unique($data['receipt_ids']));

                $detachedReceiptIds = array_values(array_diff($currentReceiptIds, $newReceiptIds));

                $reimbursement->receipts()->sync($newReceiptIds);
                $this->updateReceiptStatuses($newReceiptIds, 'pending');
                $this->updateReceiptStatuses($detachedReceiptIds, 'processed');
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

            // Reset revise → pending on re-submission (rejected terminal cannot be reset)
            if ($reimbursement->status === 'revise') {
                $updatePayload['status'] = 'pending';
                $updatePayload['rejection_comment'] = null;
                $this->updateReceiptStatuses($reimbursement->receipts()->pluck('receipts.id')->all(), 'pending');
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
            $receiptIds = $reimbursement->receipts()->pluck('receipts.id')->all();

            // Detach receipt associations
            $reimbursement->receipts()->detach();
            $this->updateReceiptStatuses($receiptIds, 'processed');

            // Remove report file from storage
            if ($reimbursement->report_file_path) {
                Storage::disk('supabase')->delete($reimbursement->report_file_path);
            }

            $reimbursement->delete();

            return true;
        });
    }
}
