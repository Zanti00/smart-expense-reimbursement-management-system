<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Users\Models\User;
use App\Modules\Reimbursements\Models\Reimbursement;
use App\Modules\Reimbursements\Models\Receipt;
use App\Modules\Reimbursements\Models\ReceiptItem;
use App\Modules\AuditLogs\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Modules\Shared\Services\PasswordVerificationService;
use Carbon\Carbon;
use Mockery;

class ReimbursementLogicTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'role' => 'employee',
            'grade' => 'L1',
            'department' => 'IT',
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'grade' => 'L5',
            'department' => 'HR',
        ]);

        Storage::fake('supabase');
    }

    public function test_default_status_is_pending(): void
    {
        $receipt = Receipt::create([
            'uploaded_by' => $this->employee->id,
            'file_path' => 'receipts/rcpt_test.png',
            'file_hash' => str_repeat('a', 64),
            'file_type' => 'png',
            'file_size_bytes' => 1024,
            'vendor_name' => 'Vendor Test',
            'total_amount' => 100.00,
        ]);

        $token = $this->generateMockToken([
            'email' => $this->employee->email,
            'role' => $this->employee->role,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/api/reimbursements', [
            'description' => 'Business Lunch',
            'category' => 'Meals',
            'amount' => 100.00,
            'date' => '2026-06-10',
            'cutoff_period' => '2026-06',
            'receipt_ids' => [$receipt->id],
            'report_file' => UploadedFile::fake()->image('report.jpg')
        ]);

        $response->assertStatus(201);
        $this->assertEquals('pending', $response->json('data.status'));
        
        $this->assertDatabaseHas('reimbursements', [
            'id' => $response->json('data.id'),
            'status' => 'pending'
        ]);
    }

    public function test_self_approval_is_prohibited(): void
    {
        // Mock external password verification to return true via Http fake
        \Illuminate\Support\Facades\Http::fake([
            '*/api/verify-password' => \Illuminate\Support\Facades\Http::response(['valid' => true], 200),
        ]);

        $reimbursement = Reimbursement::create([
            'user_id' => $this->admin->id, // Admin created their own claim
            'description' => 'Business Trip',
            'category' => 'Travel',
            'amount' => 500.00,
            'date' => Carbon::now(),
            'cutoff_period' => '2026-06',
            'status' => 'pending',
            'submitted_by_name' => $this->admin->name,
        ]);

        $token = $this->generateMockToken([
            'email' => $this->admin->email,
            'role' => $this->admin->role,
            'first_name' => 'Admin',
            'last_name' => 'User',
        ]);

        // Admin tries to approve their own claim
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/reimbursements/{$reimbursement->id}/approve", [
            'password' => 'valid_password'
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Conflict. Self-approval is strictly prohibited.']);
    }

    public function test_admin_can_update_status(): void
    {
        $reimbursement = Reimbursement::create([
            'user_id' => $this->employee->id,
            'description' => 'Office Supplies',
            'category' => 'Supplies',
            'amount' => 50.00,
            'date' => Carbon::now(),
            'cutoff_period' => '2026-06',
            'status' => 'pending',
            'submitted_by_name' => $this->employee->name,
        ]);

        $token = $this->generateMockToken([
            'email' => $this->admin->email,
            'role' => $this->admin->role,
            'first_name' => 'Admin',
            'last_name' => 'User',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson("/api/reimbursements/{$reimbursement->id}", [
            'status' => 'approved',
            'admin_notes' => 'Looks good.'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('approved', $response->json('data.status'));
        $this->assertEquals('Looks good.', $response->json('data.admin_notes'));
    }

    public function test_detail_panel_returns_accurate_data(): void
    {
        $receipt = Receipt::create([
            'uploaded_by' => $this->employee->id,
            'file_path' => 'receipts/rcpt_test.png',
            'file_hash' => str_repeat('b', 64),
            'file_type' => 'png',
            'file_size_bytes' => 1024,
            'vendor_name' => 'Data Accuracy Vendor',
            'total_amount' => 150.00,
        ]);

        ReceiptItem::create([
            'receipt_id' => $receipt->id,
            'name' => 'Item 1',
            'quantity' => 1,
            'price' => 150.00,
        ]);

        $reimbursement = Reimbursement::create([
            'user_id' => $this->employee->id,
            'description' => 'Detail Panel Test',
            'category' => 'Meals',
            'amount' => 150.00,
            'date' => Carbon::now(),
            'cutoff_period' => '2026-06',
            'status' => 'pending',
            'submitted_by_name' => $this->employee->name,
        ]);

        $reimbursement->receipts()->attach($receipt->id);

        $token = $this->generateMockToken([
            'email' => $this->employee->email,
            'role' => $this->employee->role,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/reimbursements/{$reimbursement->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'description',
            'amount',
            'status',
            'receipts' => [
                '*' => [
                    'id',
                    'vendor_name',
                    'total_amount',
                    'items' => [
                        '*' => [
                            'id',
                            'name',
                            'price'
                        ]
                    ]
                ]
            ],
            'user' => [
                'id',
                'name',
                'email'
            ]
        ]);

        $this->assertEquals('Detail Panel Test', $response->json('description'));
        $this->assertEquals('Data Accuracy Vendor', $response->json('receipts.0.vendor_name'));
        $this->assertEquals('Item 1', $response->json('receipts.0.items.0.name'));
        $this->assertEquals($this->employee->name, $response->json('user.name'));
    }

    public function test_automatic_rejection_rule(): void
    {
        $receipt = Receipt::create([
            'uploaded_by' => $this->employee->id,
            'file_path' => 'receipts/rcpt_reject.png',
            'file_hash' => str_repeat('c', 64),
            'file_type' => 'png',
            'file_size_bytes' => 1024,
            'vendor_name' => 'Rejected Vendor',
            'total_amount' => 200.00,
            'status' => 'pending',
        ]);

        $reimbursement = Reimbursement::create([
            'user_id' => $this->employee->id,
            'description' => 'Automatic Rejection Test',
            'category' => 'Meals',
            'amount' => 200.00,
            'date' => Carbon::now(),
            'cutoff_period' => '2026-06',
            'status' => 'pending',
            'submitted_by_name' => $this->employee->name,
        ]);

        $reimbursement->receipts()->attach($receipt->id);

        // Update the receipt to 'rejected' status
        // The Observer should automatically reject the linked reimbursement
        $receipt->update([
            'status' => 'rejected'
        ]);

        $reimbursement->refresh();

        $this->assertEquals('rejected', $reimbursement->status);
        $this->assertEquals('Automatically rejected because a linked receipt was rejected.', $reimbursement->admin_notes);
        $this->assertEquals('Automatically rejected because a linked receipt was rejected.', $reimbursement->rejection_comment);
    }
}
