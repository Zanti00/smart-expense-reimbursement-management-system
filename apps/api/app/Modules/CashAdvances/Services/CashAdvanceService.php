<?php

namespace App\Modules\CashAdvances\Services;

use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\CashAdvances\Models\CashAdvanceDocument;
use App\Modules\CashAdvances\Models\CashAdvanceApprovalAction;
use App\Modules\CashAdvances\Models\CashAdvanceDisbursement;
use App\Modules\CashAdvances\Models\CashAdvanceStatusHistory;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

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
            $advance->update(['status' => 'disbursed']);

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
            $advance->update([
                'signature' => $data['signature'],
                'acknowledged_at' => now(),
            ]);

            return $advance;
        });
    }
}
