<?php

namespace App\Modules\Reimbursements\Observers;

use App\Modules\Reimbursements\Models\Receipt;
use Illuminate\Support\Facades\Log;

class ReceiptStatusObserver
{
    /**
     * Handle the Receipt "updated" event.
     */
    public function updated(Receipt $receipt): void
    {
        if ($receipt->isDirty('status') && $receipt->status === 'rejected') {
            // Find all linked reimbursements via pivot
            $reimbursements = $receipt->reimbursements;

            foreach ($reimbursements as $reimbursement) {
                if ($reimbursement->status !== 'rejected') {
                    $reimbursement->update([
                        'status' => 'rejected',
                        'rejection_comment' => 'Automatically rejected because a linked receipt was rejected.',
                    ]);

                    Log::info("Reimbursement ID {$reimbursement->id} auto-rejected due to receipt ID {$receipt->id} rejection.");
                }
            }
        }
    }
}
