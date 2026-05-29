<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Users\Models\User;
use App\Modules\Reimbursements\Models\Receipt;
use App\Modules\Reimbursements\Models\Reimbursement;
use App\Modules\AuditLogs\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class WarnReceiptDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::create([
            'name' => 'Kyle L',
            'email' => 'kyle.l@example.com',
            'role' => 'employee',
            'grade' => 'L1',
            'department' => 'IT',
        ]);
    }

    public function test_receipt_deletion_warning_command_executes_successfully(): void
    {
        // 1. Receipt A: created 65 days ago, unclaimed, no warning sent yet -> should trigger warning
        $receiptA = Receipt::create([
            'uploaded_by' => $this->user->id,
            'file_path' => 'receipts/rcpt_a.png',
            'file_hash' => str_repeat('a', 64),
            'file_type' => 'png',
            'file_size_bytes' => 1024,
            'vendor_name' => 'Vendor A',
            'total_amount' => 100.00,
            'deletion_warning_sent' => false,
        ]);
        $receiptA->created_at = Carbon::now()->subDays(65);
        $receiptA->save();

        // 2. Receipt B: created 65 days ago, unclaimed, but warning already sent -> should NOT trigger warning
        $receiptB = Receipt::create([
            'uploaded_by' => $this->user->id,
            'file_path' => 'receipts/rcpt_b.png',
            'file_hash' => str_repeat('b', 64),
            'file_type' => 'png',
            'file_size_bytes' => 1024,
            'vendor_name' => 'Vendor B',
            'total_amount' => 200.00,
            'deletion_warning_sent' => true,
        ]);
        $receiptB->created_at = Carbon::now()->subDays(65);
        $receiptB->save();

        // 3. Receipt C: created 95 days ago, unclaimed, no warning sent -> past 90 days retention -> should auto soft-delete and not warn
        $receiptC = Receipt::create([
            'uploaded_by' => $this->user->id,
            'file_path' => 'receipts/rcpt_c.png',
            'file_hash' => str_repeat('c', 64),
            'file_type' => 'png',
            'file_size_bytes' => 1024,
            'vendor_name' => 'Vendor C',
            'total_amount' => 300.00,
            'deletion_warning_sent' => false,
        ]);
        $receiptC->created_at = Carbon::now()->subDays(95);
        $receiptC->save();

        // 4. Receipt D: created 65 days ago, linked to a reimbursement claim -> claimed -> should NOT warn
        $receiptD = Receipt::create([
            'uploaded_by' => $this->user->id,
            'file_path' => 'receipts/rcpt_d.png',
            'file_hash' => str_repeat('d', 64),
            'file_type' => 'png',
            'file_size_bytes' => 1024,
            'vendor_name' => 'Vendor D',
            'total_amount' => 400.00,
            'deletion_warning_sent' => false,
        ]);
        $receiptD->created_at = Carbon::now()->subDays(65);
        $receiptD->save();

        Reimbursement::create([
            'user_id' => $this->user->id,
            'receipt_id' => $receiptD->id,
            'description' => 'Business Dinner',
            'category' => 'Meals',
            'amount' => 400.00,
            'date' => Carbon::now()->subDays(65),
            'status' => 'pending',
        ]);

        // 5. Receipt E: created 10 days ago, unclaimed -> new staging -> should NOT warn
        $receiptE = Receipt::create([
            'uploaded_by' => $this->user->id,
            'file_path' => 'receipts/rcpt_e.png',
            'file_hash' => str_repeat('e', 64),
            'file_type' => 'png',
            'file_size_bytes' => 1024,
            'vendor_name' => 'Vendor E',
            'total_amount' => 500.00,
            'deletion_warning_sent' => false,
        ]);
        $receiptE->created_at = Carbon::now()->subDays(10);
        $receiptE->save();

        // Run the Artisan command
        $exitCode = Artisan::call('receipts:warn-deletion');
        $this->assertEquals(0, $exitCode);

        // --- ASSERTIONS ---

        // Assert Receipt A: Deletion warning sent flag updated to true
        $receiptA->refresh();
        $this->assertTrue($receiptA->deletion_warning_sent);

        // Assert Receipt B: Unchanged
        $receiptB->refresh();
        $this->assertTrue($receiptB->deletion_warning_sent);

        // Assert Receipt C: Soft deleted (does not exist in active query, exists in trashed query)
        $this->assertNull(Receipt::find($receiptC->id));
        $this->assertNotNull(Receipt::withTrashed()->find($receiptC->id));
        $this->assertNotNull(Receipt::withTrashed()->find($receiptC->id)->deleted_at);

        // Assert Receipt D: Unchanged and warning NOT sent
        $receiptD->refresh();
        $this->assertFalse($receiptD->deletion_warning_sent);

        // Assert Receipt E: Unchanged and warning NOT sent
        $receiptE->refresh();
        $this->assertFalse($receiptE->deletion_warning_sent);

        // --- COMPLIANCE AUDIT LOG ASSERTIONS ---

        // Assert warning audit log was created for Receipt A
        $this->assertTrue(AuditLog::where('action_type', 'RECEIPT_DELETION_WARNING_SENT')
            ->where('entity_type', 'Receipt')
            ->where('entity_id', $receiptA->id)
            ->exists());

        // Assert auto-deletion audit log was created for Receipt C
        $this->assertTrue(AuditLog::where('action_type', 'RECEIPT_SYSTEM_DELETED')
            ->where('entity_type', 'Receipt')
            ->where('entity_id', $receiptC->id)
            ->exists());

        // Assert NO audit log created for Receipt B, D, or E
        $this->assertFalse(AuditLog::where('action_type', 'RECEIPT_DELETION_WARNING_SENT')
            ->where('entity_id', $receiptB->id)
            ->exists());
        $this->assertFalse(AuditLog::where('action_type', 'RECEIPT_DELETION_WARNING_SENT')
            ->where('entity_id', $receiptD->id)
            ->exists());
        $this->assertFalse(AuditLog::where('action_type', 'RECEIPT_DELETION_WARNING_SENT')
            ->where('entity_id', $receiptE->id)
            ->exists());
    }
}
