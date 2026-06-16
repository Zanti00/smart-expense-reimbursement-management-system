<?php

namespace App\Modules\CashAdvances\Services;

use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\CashAdvances\Models\CashAdvanceDocument;
use App\Modules\CashAdvances\Models\CashAdvanceApprovalAction;
use App\Modules\CashAdvances\Models\CashAdvanceDisbursement;
use App\Modules\CashAdvances\Models\CashAdvanceStatusHistory;
use App\Modules\Users\Models\User;
use App\Modules\Shared\Services\PasswordVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CashAdvanceService
{
    public function createAdvance(User $user, array $data, array $files = [])
    {
        return DB::transaction(function () use ($user, $data, $files) {
            $advance = CashAdvance::create([
                'user_id' => $user->id,
                'purpose' => $data['purpose'],
                'amount' => $data['amount'],
                'expected_disbursement_date' => $data['expected_disbursement_date'],
                'expected_liquidation_date' => $data['expected_liquidation_date'],
                'status' => 'pending',
            ]);

            CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => null,
                'to_status' => 'pending',
                'changed_by' => $user->id,
            ]);

            foreach ($files as $file) {
                $path = $file->store('cash_advances/documents', 'supabase');

                CashAdvanceDocument::create([
                    'cash_advance_id' => $advance->id,
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                    'file_name' => $file->getClientOriginalName(),
                ]);
            }

            return $advance;
        });
    }

    public function approveAdvance(CashAdvance $advance, User $user, ?string $comment = null)
    {
        return DB::transaction(function () use ($advance, $user, $comment) {
            $advance->update(['status' => 'approved']);

            CashAdvanceApprovalAction::create([
                'cash_advance_id' => $advance->id,
                'approver_id' => $user->id,
                'action' => 'approved',
                'comment' => $comment,
            ]);

            CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => 'pending',
                'to_status' => 'approved',
                'changed_by' => $user->id,
            ]);

            return $advance;
        });
    }

    public function rejectAdvance(CashAdvance $advance, User $user, string $comment)
    {
        return DB::transaction(function () use ($advance, $user, $comment) {
            $advance->update(['status' => 'rejected']);

            CashAdvanceApprovalAction::create([
                'cash_advance_id' => $advance->id,
                'approver_id' => $user->id,
                'action' => 'rejected',
                'comment' => $comment,
            ]);

            CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => 'pending',
                'to_status' => 'rejected',
                'changed_by' => $user->id,
            ]);

            return $advance;
        });
    }

    public function disburseAdvance(CashAdvance $advance, User $user, array $data)
    {
        return DB::transaction(function () use ($advance, $user, $data) {
            $advance->update([
                'status'              => 'disbursed',
                'outstanding_balance' => $advance->amount, // Debt begins at full amount
            ]);

            CashAdvanceDisbursement::create([
                'cash_advance_id' => $advance->id,
                'disbursed_by_id' => $user->id,
                'disbursement_date' => now()->toDateString(),
                'channel' => $data['channel'],
                'reference_number' => $data['reference'],
            ]);

            CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => 'approved',
                'to_status' => 'disbursed',
                'changed_by' => $user->id,
            ]);

            return $advance;
        });
    }

    public function acknowledgeAdvance(CashAdvance $advance, array $data)
    {
        return DB::transaction(function () use ($advance, $data) {
            $oldStatus = $advance->status;

            $advance->update([
                'signature' => $data['signature'],
                'acknowledged_at' => now(),
                'status' => 'signed',
            ]);

            CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => $oldStatus,
                'to_status' => 'signed',
                'changed_by' => auth()->id() ?? $advance->user_id,
            ]);

            return $advance;
        });
    }

    /**
     * Update a pending or rejected cash advance (employee self-edit).
     *
     * Resets rejected → pending with status history entry.
     */
    public function updateAdvance(CashAdvance $advance, User $user, array $data, array $files = [])
    {
        return DB::transaction(function () use ($advance, $user, $data, $files) {
            $oldStatus = $advance->status;

            $updatePayload = array_filter([
                'purpose' => $data['purpose'] ?? null,
                'amount' => $data['amount'] ?? null,
                'expected_disbursement_date' => $data['expected_disbursement_date'] ?? null,
                'expected_liquidation_date' => $data['expected_liquidation_date'] ?? null,
            ], fn($v) => $v !== null);

            // Reset rejected → pending on re-submission
            if ($advance->status === 'rejected') {
                $updatePayload['status'] = 'pending';
            }

            $advance->update($updatePayload);

            // Handle new document uploads — replace existing documents
            if (!empty($files)) {
                // Delete old documents from storage
                foreach ($advance->document()->get() as $doc) {
                    Storage::disk('supabase')->delete($doc->file_path);
                    $doc->delete();
                }

                // Upload new documents
                foreach ($files as $file) {
                    $path = $file->store('cash_advances/documents', 'supabase');
                    CashAdvanceDocument::create([
                        'cash_advance_id' => $advance->id,
                        'file_path' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'file_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            // Record status transition if status changed
            if ($oldStatus !== $advance->status) {
                CashAdvanceStatusHistory::create([
                    'cash_advance_id' => $advance->id,
                    'from_status' => $oldStatus,
                    'to_status' => $advance->status,
                    'changed_by' => $user->id,
                ]);
            }

            return $advance;
        });
    }

    /**
     * Delete a pending cash advance request.
     *
     * Only the owner can delete, and only when status is pending.
     */
    public function deleteAdvance(CashAdvance $advance, User $user, string $password, Request $requestContext)
    {
        // Verify password against external auth service
        if (!PasswordVerificationService::verify($requestContext, $password)) {
            throw ValidationException::withMessages([
                'password' => ['Invalid password. Please try again.']
            ]);
        }

        return DB::transaction(function () use ($advance) {
            // Delete documents from storage
            foreach ($advance->document()->get() as $doc) {
                Storage::disk('supabase')->delete($doc->file_path);
                $doc->delete();
            }

            // Delete related records
            $advance->approvalActions()->delete();
            $advance->statusHistory()->delete();

            $advance->delete();

            return true;
        });
    }
}
