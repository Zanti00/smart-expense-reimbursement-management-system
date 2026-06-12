<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Users\Models\User;
use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\Liquidations\Models\Liquidation;
use App\Modules\Reimbursements\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class LiquidationLogicTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $admin;
    private CashAdvance $cashAdvance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::create([
            'name' => 'John Employee',
            'email' => 'john.employee@example.com',
            'role' => 'employee',
            'grade' => 'L2',
            'department' => 'Sales',
        ]);

        $this->admin = User::create([
            'name' => 'Jane Admin',
            'email' => 'jane.admin@example.com',
            'role' => 'admin',
            'grade' => 'L5',
            'department' => 'HR',
        ]);

        $this->cashAdvance = CashAdvance::create([
            'user_id' => $this->employee->id,
            'purpose' => 'Business travel to Cebu',
            'amount' => 5000.00,
            'status' => 'disbursed',
            'expected_disbursement_date' => Carbon::now()->addDays(2),
            'expected_liquidation_date' => Carbon::now()->addDays(7),
        ]);

        Storage::fake('supabase');
    }

    public function test_submit_liquidation_transitions_cash_advance_status(): void
    {
        $receipt = Receipt::create([
            'uploaded_by' => $this->employee->id,
            'file_path' => 'receipts/test_ocr.png',
            'file_hash' => str_repeat('d', 64),
            'file_type' => 'png',
            'file_size_bytes' => 512,
            'vendor_name' => 'Original Vendor',
            'total_amount' => 5000.00,
        ]);

        $token = $this->generateMockToken([
            'email' => $this->employee->email,
            'role' => $this->employee->role,
            'first_name' => 'John',
            'last_name' => 'Employee',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/liquidations', [
            'cash_advance_id' => $this->cashAdvance->id,
            'receipts' => [
                [
                    'id' => $receipt->id,
                    'vendor_name' => 'Edited Vendor',
                    'transaction_date' => '2026-06-12',
                    'total_amount' => 5000.00,
                    'vat_amount' => 600.00,
                    'tin' => '123-456-789-000',
                    'invoice_number' => 'INV-9999',
                ]
            ],
            'total_expense_amount' => 5000.00,
        ]);

        $response->assertStatus(210);

        // Verify cash advance is locked/approved
        $this->cashAdvance->refresh();
        $this->assertEquals('approved', $this->cashAdvance->status);

        // Verify receipt is updated
        $receipt->refresh();
        $this->assertEquals('Edited Vendor', $receipt->vendor_name);
        $this->assertEquals('pending', $receipt->status);

        // Verify liquidation is stored
        $this->assertDatabaseHas('liquidations', [
            'cash_advance_id' => $this->cashAdvance->id,
            'user_id' => $this->employee->id,
            'status' => 'pending',
            'total_expense_amount' => 5000.00,
            'variance_amount' => 0.00,
        ]);
    }

    public function test_submit_liquidation_requires_shortfall_explanation(): void
    {
        $receipt = Receipt::create([
            'uploaded_by' => $this->employee->id,
            'file_path' => 'receipts/test_ocr.png',
            'file_hash' => str_repeat('d', 64),
            'file_type' => 'png',
            'file_size_bytes' => 512,
            'vendor_name' => 'Original Vendor',
            'total_amount' => 3000.00,
        ]);

        $token = $this->generateMockToken([
            'email' => $this->employee->email,
            'role' => $this->employee->role,
            'first_name' => 'John',
            'last_name' => 'Employee',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/liquidations', [
            'cash_advance_id' => $this->cashAdvance->id,
            'receipts' => [
                [
                    'id' => $receipt->id,
                    'vendor_name' => 'Original Vendor',
                    'transaction_date' => '2026-06-12',
                    'total_amount' => 3000.00,
                ]
            ],
            'total_expense_amount' => 3000.00,
            // shortfall_explanation is intentionally missing when variance is 2000
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['shortfall_explanation']);
    }

    public function test_audit_liquidation_approval_updates_cash_advance_to_settled(): void
    {
        Http::fake([
            '*/api/verify-password' => Http::response(['valid' => true], 200),
        ]);

        $liquidation = Liquidation::create([
            'cash_advance_id' => $this->cashAdvance->id,
            'user_id' => $this->employee->id,
            'status' => 'pending',
            'reimbursement_ids' => [99],
            'total_expense_amount' => 5000.00,
            'variance_amount' => 0.00,
        ]);

        $token = $this->generateMockToken([
            'email' => $this->admin->email,
            'role' => $this->admin->role,
            'first_name' => 'Jane',
            'last_name' => 'Admin',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/liquidations/{$liquidation->id}/audit", [
            'status' => 'approved',
            'password' => 'supersecret',
        ]);

        $response->assertStatus(200);

        $liquidation->refresh();
        $this->assertEquals('liquidated', $liquidation->status);

        $this->cashAdvance->refresh();
        $this->assertEquals('settled', $this->cashAdvance->status);
    }

    public function test_audit_liquidation_rejection_requires_admin_note(): void
    {
        Http::fake([
            '*/api/verify-password' => Http::response(['valid' => true], 200),
        ]);

        $liquidation = Liquidation::create([
            'cash_advance_id' => $this->cashAdvance->id,
            'user_id' => $this->employee->id,
            'status' => 'pending',
            'reimbursement_ids' => [99],
            'total_expense_amount' => 5000.00,
            'variance_amount' => 0.00,
        ]);

        $token = $this->generateMockToken([
            'email' => $this->admin->email,
            'role' => $this->admin->role,
            'first_name' => 'Jane',
            'last_name' => 'Admin',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/liquidations/{$liquidation->id}/audit", [
            'status' => 'rejected',
            'password' => 'supersecret',
            // admin_note missing
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['admin_note']);

        // Send short admin note
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/liquidations/{$liquidation->id}/audit", [
            'status' => 'rejected',
            'password' => 'supersecret',
            'admin_note' => 'no', // < 5 chars
        ]);

        $response->assertStatus(422);
    }
}
