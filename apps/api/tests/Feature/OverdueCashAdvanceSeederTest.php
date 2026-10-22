<?php

namespace Tests\Feature;

use App\Modules\CashAdvances\Database\Seeders\OverdueCashAdvanceSeeder;
use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverdueCashAdvanceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_provisions_overdue_advances_with_overpayment(): void
    {
        $this->seed(OverdueCashAdvanceSeeder::class);

        $employee = User::where('email', OverdueCashAdvanceSeeder::EMPLOYEE_EMAIL)->first();
        $this->assertNotNull($employee);

        $admin = User::where('email', OverdueCashAdvanceSeeder::ADMIN_EMAIL)->first();
        $this->assertNotNull($admin);

        // Employee overdue with overpayment
        $empAdvance = CashAdvance::where('user_id', $employee->id)
            ->where('purpose', 'Regional Client Onsite & Setup — Overdue with Overpayment')
            ->first();

        $this->assertNotNull($empAdvance);
        $this->assertEquals('overdue', $empAdvance->status);
        $this->assertEquals(10000.00, (float) $empAdvance->amount);
        $this->assertGreaterThan(0, $empAdvance->penalties()->count());

        $liq = $empAdvance->liquidations()->first();
        $this->assertNotNull($liq);
        $this->assertEquals(12500.00, (float) $liq->total_expense_amount);
        $this->assertGreaterThan((float) $empAdvance->amount, (float) $liq->total_expense_amount);

        // Admin overdue with overpayment
        $admAdvance = CashAdvance::where('user_id', $admin->id)
            ->where('purpose', 'Accounting Summit Travel & Venue — Overdue with Overpayment')
            ->first();

        $this->assertNotNull($admAdvance);
        $this->assertEquals('overdue', $admAdvance->status);
        $this->assertEquals(15000.00, (float) $admAdvance->amount);

        // Idempotency check: running again shouldn't fail or duplicate
        $this->seed(OverdueCashAdvanceSeeder::class);
        $this->assertEquals(
            1,
            CashAdvance::where('user_id', $employee->id)
                ->where('purpose', 'Regional Client Onsite & Setup — Overdue with Overpayment')
                ->count()
        );
    }
}
