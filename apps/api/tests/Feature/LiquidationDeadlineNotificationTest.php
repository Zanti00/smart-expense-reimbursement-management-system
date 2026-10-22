<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Users\Models\User;
use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\Liquidations\Models\Liquidation;
use App\Modules\Notifications\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class LiquidationDeadlineNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@sbsi.com',
            'role' => 'employee',
            'department' => 'Operations',
        ]);

        $this->admin = User::create([
            'name' => 'Maria Admin',
            'email' => 'maria@sbsi.com',
            'role' => 'admin',
            'department' => 'Accounting',
        ]);
    }

    public function test_due_soon_and_overdue_notifications_are_created_once_and_skip_settled(): void
    {
        // 1. Due soon (2 days left)
        $dueSoonAdvance = CashAdvance::create([
            'user_id' => $this->employee->id,
            'purpose' => 'Due Soon Trip',
            'amount' => 5000.00,
            'outstanding_balance' => 5000.00,
            'expected_disbursement_date' => now()->subDays(5)->toDateString(),
            'expected_liquidation_date' => now()->addDays(2)->toDateString(),
            'status' => 'signed',
            'acknowledged_at' => now()->subDays(4),
        ]);

        // 2. Overdue (3 days past due)
        $overdueAdvance = CashAdvance::create([
            'user_id' => $this->employee->id,
            'purpose' => 'Overdue Field Work',
            'amount' => 8000.00,
            'outstanding_balance' => 8000.00,
            'expected_disbursement_date' => now()->subDays(10)->toDateString(),
            'expected_liquidation_date' => now()->subDays(3)->toDateString(),
            'status' => 'signed',
            'acknowledged_at' => now()->subDays(9),
        ]);

        // 3. Already liquidated/settled (should NEVER be flagged)
        $settledAdvance = CashAdvance::create([
            'user_id' => $this->employee->id,
            'purpose' => 'Settled Work',
            'amount' => 3000.00,
            'outstanding_balance' => 0.00,
            'expected_disbursement_date' => now()->subDays(12)->toDateString(),
            'expected_liquidation_date' => now()->subDays(5)->toDateString(),
            'status' => 'liquidated',
            'acknowledged_at' => now()->subDays(11),
        ]);
        Liquidation::create([
            'cash_advance_id' => $settledAdvance->id,
            'user_id' => $this->employee->id,
            'status' => 'liquidated',
            'reimbursement_ids' => [],
            'total_expense_amount' => 3000.00,
            'outstanding_balance' => 0.00,
        ]);

        // Run penalties:calculate (which calculates penalties and runs deadline notifications)
        Artisan::call('penalties:calculate');

        // Due soon creates 1 warning for employee
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->employee->id,
            'type' => 'liquidation_due_soon',
            'record_type' => 'cash_advance',
            'record_id' => $dueSoonAdvance->id,
        ]);

        // Overdue creates 1 notification for employee and 1 for admin with days overdue and penalty (₱150.00)
        $empOverdue = Notification::where('user_id', $this->employee->id)
            ->where('type', 'liquidation_overdue')
            ->where('record_id', $overdueAdvance->id)
            ->first();
        $this->assertNotNull($empOverdue);
        $this->assertStringContainsString('3 days overdue', $empOverdue->message);
        $this->assertStringContainsString('₱150.00', $empOverdue->message);

        $adminOverdue = Notification::where('user_id', $this->admin->id)
            ->where('type', 'admin_liquidation_overdue')
            ->where('record_id', $overdueAdvance->id)
            ->first();
        $this->assertNotNull($adminOverdue);
        $this->assertStringContainsString('Juan Dela Cruz', $adminOverdue->message);
        $this->assertStringContainsString('₱8,000.00', $adminOverdue->message);
        $this->assertStringContainsString('3 days overdue', $adminOverdue->message);
        $this->assertStringContainsString('₱150.00', $adminOverdue->message);

        // Settled advance is never flagged
        $this->assertDatabaseMissing('notifications', [
            'record_type' => 'cash_advance',
            'record_id' => $settledAdvance->id,
        ]);

        // Running a second time does not create duplicate overdue or due-soon notifications
        Artisan::call('notifications:check-liquidation-deadlines');
        $this->assertEquals(1, Notification::where('user_id', $this->admin->id)->where('record_id', $overdueAdvance->id)->count());
        $this->assertEquals(1, Notification::where('user_id', $this->employee->id)->where('record_id', $overdueAdvance->id)->count());
    }
}
