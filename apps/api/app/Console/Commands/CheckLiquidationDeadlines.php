<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\Liquidations\Models\PenaltyRecord;
use App\Modules\Notifications\Services\NotificationService;
use Carbon\Carbon;

class CheckLiquidationDeadlines extends Command
{
    protected $signature = 'notifications:check-liquidation-deadlines';

    protected $description = 'Send Due Soon (<= 3 days) and Overdue liquidation notifications to employees and admins.';

    public function handle(): int
    {
        $today = Carbon::now()->startOfDay();

        $advances = CashAdvance::with(['requester', 'liquidations'])
            ->whereIn('status', ['disbursed', 'signed', 'incomplete', 'overdue'])
            ->whereNotNull('expected_liquidation_date')
            ->where('outstanding_balance', '>', 0)
            ->get();

        foreach ($advances as $advance) {
            // Skip if any linked liquidation is already settled/liquidated/approved/under-review/pending
            $hasSettledOrActiveAudit = $advance->liquidations->contains(function ($liq) {
                return in_array(strtolower((string) $liq->status), ['liquidated', 'settled', 'approved', 'pending', 'under-review'], true);
            });

            if ($hasSettledOrActiveAudit) {
                continue;
            }

            $dueDate = Carbon::parse($advance->expected_liquidation_date)->startOfDay();
            $daysLeft = (int) $today->diffInDays($dueDate, false);
            $formattedDue = $dueDate->format('M j, Y');
            $formattedAmount = NotificationService::formatPeso($advance->amount);
            $employeeName = $advance->requester?->name ?? 'Employee';
            $latestLiq = $advance->liquidations->sortByDesc('id')->first();
            $recordLink = $latestLiq
                ? "/liquidations?view={$latestLiq->id}"
                : "/liquidations?advance={$advance->id}";

            if ($daysLeft >= 0 && $daysLeft <= 3) {
                // Due Soon warning for employee (G2-071 Task 3)
                $whenLabel = $daysLeft === 0 ? 'due today' : "due in {$daysLeft} " . ($daysLeft === 1 ? 'day' : 'days');
                NotificationService::notifyOnce(
                    userId: (int) $advance->user_id,
                    type: 'liquidation_due_soon',
                    module: 'Liquidation',
                    title: 'Liquidation Due Soon',
                    message: "Your liquidation for Cash Advance #{$advance->id} ({$formattedAmount}) is {$whenLabel} ({$formattedDue}).",
                    recordLink: $recordLink,
                    recordType: 'cash_advance',
                    recordId: (int) $advance->id,
                    metadata: [
                        'days_left' => $daysLeft,
                        'due_date' => $dueDate->toDateString(),
                    ]
                );
            } elseif ($daysLeft < 0) {
                // Overdue alert for employee and all admins (G2-071 Task 2 & 3)
                $daysOverdue = abs($daysLeft);
                $penaltySum = (float) PenaltyRecord::where('cash_advance_id', $advance->id)->sum('penalty_amount');
                if ($penaltySum <= 0) {
                    $penaltySum = $daysOverdue * 50.00;
                }
                $formattedPenalty = NotificationService::formatPeso($penaltySum);
                $dayWord = $daysOverdue === 1 ? 'day' : 'days';

                NotificationService::notifyOnce(
                    userId: (int) $advance->user_id,
                    type: 'liquidation_overdue',
                    module: 'Liquidation',
                    title: 'Liquidation Overdue',
                    message: "Your liquidation for Cash Advance #{$advance->id} ({$formattedAmount}) is {$daysOverdue} {$dayWord} overdue (Due: {$formattedDue}). Current penalty: {$formattedPenalty}.",
                    recordLink: $recordLink,
                    recordType: 'cash_advance',
                    recordId: (int) $advance->id,
                    metadata: [
                        'days_overdue' => $daysOverdue,
                        'penalty_amount' => $penaltySum,
                    ]
                );

                NotificationService::notifyAdminsOnce(
                    type: 'admin_liquidation_overdue',
                    module: 'Liquidation',
                    title: 'Overdue Liquidation Alert',
                    message: "{$employeeName}'s liquidation for Cash Advance #{$advance->id} ({$formattedAmount}) is {$daysOverdue} {$dayWord} overdue. Accrued penalty: {$formattedPenalty}.",
                    recordLink: $recordLink,
                    recordType: 'cash_advance',
                    recordId: (int) $advance->id,
                    metadata: [
                        'employee_name' => $employeeName,
                        'days_overdue' => $daysOverdue,
                        'penalty_amount' => $penaltySum,
                    ],
                    excludeUserId: (int) $advance->user_id
                );
            }
        }

        return Command::SUCCESS;
    }
}
