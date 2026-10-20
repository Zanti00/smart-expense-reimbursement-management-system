<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Users\Models\User;
use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\CashAdvances\Models\CashAdvanceDisbursement;
use App\Modules\CashAdvances\Models\CashAdvanceStatusHistory;
use App\Modules\CashAdvances\Database\Seeders\LiquidationReadyCashAdvanceSeeder;
use App\Modules\Liquidations\Models\Liquidation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiquidationReadyCashAdvanceSeederTest extends TestCase
{
    use RefreshDatabase;

    private function createTargetAdmin(): User
    {
        return User::create([
            'name' => 'Accounting Admin',
            'email' => LiquidationReadyCashAdvanceSeeder::TARGET_EMAIL,
            'role' => 'admin',
            'department' => 'Accounting',
        ]);
    }

    public function test_it_seeds_six_signed_acknowledged_advances_ready_for_liquidation(): void
    {
        $admin = $this->createTargetAdmin();

        $this->seed(LiquidationReadyCashAdvanceSeeder::class);

        $advances = CashAdvance::with('disbursement')
            ->where('user_id', $admin->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(6, $advances);
        $this->assertSame(6, $advances->pluck('purpose')->unique()->count());

        $referenceNumbers = [];

        foreach ($advances as $advance) {
            $this->assertSame('signed', $advance->status);
            $this->assertNotNull($advance->acknowledged_at);
            $this->assertGreaterThan(0, (float) $advance->amount);
            $this->assertSame(
                (float) $advance->amount,
                (float) $advance->outstanding_balance
            );
            $this->assertTrue(
                $advance->expected_liquidation_date->isAfter(Carbon::today()),
                'Seeded advance must not already be overdue.'
            );
            $this->assertNotNull($advance->disbursement);

            $referenceNumbers[] = $advance->disbursement->reference_number;

            $historyStatuses = $advance->statusHistory()
                ->orderBy('id')
                ->pluck('to_status')
                ->all();

            $this->assertSame(
                ['pending', 'approved', 'disbursed', 'signed'],
                $historyStatuses
            );
        }

        $this->assertCount(6, array_unique($referenceNumbers));
    }

    public function test_running_the_seeder_twice_does_not_duplicate_the_advances_or_their_history(): void
    {
        $admin = $this->createTargetAdmin();

        $this->seed(LiquidationReadyCashAdvanceSeeder::class);
        $this->seed(LiquidationReadyCashAdvanceSeeder::class);

        $advanceIds = CashAdvance::where('user_id', $admin->id)->pluck('id');

        $this->assertSame(
            6,
            CashAdvance::where('user_id', $admin->id)->count()
        );
        $this->assertSame(
            24,
            CashAdvanceStatusHistory::whereIn('cash_advance_id', $advanceIds)->count()
        );
        $this->assertSame(
            6,
            CashAdvanceDisbursement::whereIn('cash_advance_id', $advanceIds)->count()
        );
    }

    public function test_it_does_not_reset_an_advance_that_already_has_a_liquidation(): void
    {
        $admin = $this->createTargetAdmin();

        $this->seed(LiquidationReadyCashAdvanceSeeder::class);

        $advance = CashAdvance::where('user_id', $admin->id)
            ->orderBy('id')
            ->firstOrFail();

        Liquidation::create([
            'cash_advance_id' => $advance->id,
            'user_id' => $admin->id,
            'status' => 'pending',
            'reimbursement_ids' => [],
            'total_expense_amount' => 1000.00,
            'outstanding_balance' => 2500.00,
        ]);

        $advance->update([
            'status' => 'under-review',
            'outstanding_balance' => 2500.00,
        ]);

        $this->seed(LiquidationReadyCashAdvanceSeeder::class);

        $advance->refresh();

        $this->assertSame('under-review', $advance->status);
        $this->assertSame(2500.0, (float) $advance->outstanding_balance);
    }

    public function test_it_fails_with_a_clear_message_when_the_target_admin_does_not_exist(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(LiquidationReadyCashAdvanceSeeder::TARGET_EMAIL);

        (new LiquidationReadyCashAdvanceSeeder())->run();
    }
}
