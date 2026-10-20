<?php

namespace App\Modules\CashAdvances\Database\Seeders;

use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds six signed, acknowledged cash advances owned by an Accounting admin so
 * the admin-side "New Request" liquidation flow has eligible advances to test
 * with.
 *
 * Run explicitly:
 *   php artisan db:seed --class="App\Modules\CashAdvances\Database\Seeders\LiquidationReadyCashAdvanceSeeder"
 */
class LiquidationReadyCashAdvanceSeeder extends Seeder
{
    public const TARGET_EMAIL = 'sum@sbsi.com';

    /**
     * @var array<int, array{purpose: string, amount: float, reference: string}>
     */
    private const ADVANCE_DEFINITIONS = [
        [
            'purpose' => 'Admin liquidation QA — client meetings and supplies',
            'amount' => 5000.00,
            'reference' => 'SEED-ADMIN-LIQ-001',
        ],
        [
            'purpose' => 'Admin liquidation QA — regional client visit',
            'amount' => 2750.00,
            'reference' => 'SEED-ADMIN-LIQ-002',
        ],
        [
            'purpose' => 'Admin liquidation QA — team offsite expenses',
            'amount' => 3400.00,
            'reference' => 'SEED-ADMIN-LIQ-003',
        ],
        [
            'purpose' => 'Admin liquidation QA — supplier meeting',
            'amount' => 4200.00,
            'reference' => 'SEED-ADMIN-LIQ-004',
        ],
        [
            'purpose' => 'Admin liquidation QA — quarterly review travel',
            'amount' => 6100.00,
            'reference' => 'SEED-ADMIN-LIQ-005',
        ],
        [
            'purpose' => 'Admin liquidation QA — office replenishment',
            'amount' => 7800.00,
            'reference' => 'SEED-ADMIN-LIQ-006',
        ],
    ];

    public function run(): void
    {
        $admin = User::where('email', self::TARGET_EMAIL)->first();

        if (! $admin) {
            throw new \RuntimeException(
                'Cannot seed liquidation-ready cash advances: user '
                . self::TARGET_EMAIL
                . ' was not found.'
            );
        }

        foreach (self::ADVANCE_DEFINITIONS as $definition) {
            $this->seedAdvance($admin, $definition);
        }
    }

    /**
     * @param  array{purpose: string, amount: float, reference: string}  $definition
     */
    private function seedAdvance(User $admin, array $definition): void
    {
        $advance = CashAdvance::where('user_id', $admin->id)
            ->where('purpose', $definition['purpose'])
            ->first();

        if ($advance) {
            if ($advance->liquidations()->exists()) {
                $this->warn(
                    "Cash advance #{$advance->id} already has a liquidation; leaving it untouched."
                );

                return;
            }

            $this->armForLiquidation($advance, $definition);

            $this->info(
                "Cash advance #{$advance->id} is ready for liquidation (re-armed, ₱"
                . number_format($definition['amount'], 2)
                . ').'
            );

            return;
        }

        $submittedAt = Carbon::now()->subDays(5);

        $advance = CashAdvance::create([
            'user_id' => $admin->id,
            'purpose' => $definition['purpose'],
            'amount' => $definition['amount'],
            'outstanding_balance' => $definition['amount'],
            'expected_disbursement_date' => Carbon::today()->subDays(4),
            'expected_liquidation_date' => Carbon::today()->addDays(14),
            'status' => 'signed',
            'signature' => 'SEEDED-SIGNATURE',
            'acknowledged_at' => Carbon::now()->subDays(2),
        ]);

        $advance->submitted_at = $submittedAt;
        $advance->save();

        $this->seedStatusHistory($advance, $admin, $submittedAt);

        $advance->disbursement()->create([
            'disbursed_by_id' => $admin->id,
            'disbursement_date' => Carbon::today()->subDays(3),
            'channel' => 'bank-transfer',
            'reference_number' => $definition['reference'],
        ]);

        $this->info(
            "Created liquidation-ready cash advance #{$advance->id} for "
            . self::TARGET_EMAIL
            . ' (₱' . number_format($definition['amount'], 2) . ').'
        );
    }

    /**
     * @param  array{purpose: string, amount: float, reference: string}  $definition
     */
    private function armForLiquidation(CashAdvance $advance, array $definition): void
    {
        $advance->forceFill([
            'status' => 'signed',
            'amount' => $definition['amount'],
            'outstanding_balance' => $definition['amount'],
            'expected_liquidation_date' => Carbon::today()->addDays(14),
            'acknowledged_at' => Carbon::now()->subDays(2),
        ])->save();
    }

    private function seedStatusHistory(
        CashAdvance $advance,
        User $admin,
        Carbon $submittedAt
    ): void {
        $transitions = [
            ['from' => null, 'to' => 'pending', 'at' => $submittedAt->copy()],
            ['from' => 'pending', 'to' => 'approved', 'at' => $submittedAt->copy()->addDay()],
            ['from' => 'approved', 'to' => 'disbursed', 'at' => $submittedAt->copy()->addDays(2)],
            ['from' => 'disbursed', 'to' => 'signed', 'at' => $submittedAt->copy()->addDays(3)],
        ];

        foreach ($transitions as $transition) {
            $advance->statusHistory()->create([
                'from_status' => $transition['from'],
                'to_status' => $transition['to'],
                'changed_by' => $admin->id,
                'changed_at' => $transition['at'],
            ]);
        }
    }

    private function warn(string $message): void
    {
        $this->command?->warn($message);
    }

    private function info(string $message): void
    {
        $this->command?->info($message);
    }
}
