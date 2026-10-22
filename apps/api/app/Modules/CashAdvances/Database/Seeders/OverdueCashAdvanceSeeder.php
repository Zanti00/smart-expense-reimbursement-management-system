<?php

namespace App\Modules\CashAdvances\Database\Seeders;

use App\Modules\AuditLogs\Services\AuditLogService;
use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\CashAdvances\Models\CashAdvanceApprovalAction;
use App\Modules\CashAdvances\Models\CashAdvanceDisbursement;
use App\Modules\CashAdvances\Models\CashAdvanceStatusHistory;
use App\Modules\Liquidations\Models\Liquidation;
use App\Modules\Liquidations\Models\PenaltyRecord;
use App\Modules\Reimbursements\Models\ExpenseCategory;
use App\Modules\Reimbursements\Models\Receipt;
use App\Modules\Reimbursements\Models\ReceiptItem;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OverdueCashAdvanceSeeder extends Seeder
{
    public const EMPLOYEE_EMAIL = 'employee@example.com';
    public const ADMIN_EMAIL = 'sum@sbsi.com';

    public function run(): void
    {
        $employee = User::firstOrCreate(
            ['email' => self::EMPLOYEE_EMAIL],
            [
                'name' => 'General Employee',
                'role' => 'employee',
                'department' => 'Operations',
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'summer love',
                'role' => 'admin',
                'department' => 'Accounting',
            ]
        );

        ExpenseCategory::ensureDefaults();
        $category = ExpenseCategory::where('name', 'Meals')->first() ?? ExpenseCategory::first();
        $categoryId = $category ? $category->id : 1;

        // 1. Employee Overdue Cash Advance with Overpayment (>6 steps, 2 rows)
        $this->seedOverdueAdvanceWithOverpayment(
            user: $employee,
            approver: $admin,
            purpose: 'Regional Client Onsite & Setup — Overdue with Overpayment',
            advanceAmount: 10000.00,
            expenseAmount: 12500.00, // 2500 overpayment
            overdueDays: 5,
            reference: 'SEED-OVD-EMP-001',
            invoiceNumber: 'INV-OVD-EMP-001',
            categoryId: $categoryId
        );

        // 2. Admin Overdue Cash Advance with Overpayment (>6 steps, 2 rows)
        $this->seedOverdueAdvanceWithOverpayment(
            user: $admin,
            approver: $admin,
            purpose: 'Accounting Summit Travel & Venue — Overdue with Overpayment',
            advanceAmount: 15000.00,
            expenseAmount: 18000.00, // 3000 overpayment
            overdueDays: 4,
            reference: 'SEED-OVD-ADM-001',
            invoiceNumber: 'INV-OVD-ADM-001',
            categoryId: $categoryId
        );

        // 3. Employee Standard Overdue Advance (6 steps)
        $this->seedStandardOverdueAdvance(
            user: $employee,
            approver: $admin,
            purpose: 'Equipment Calibration & Maintenance — Overdue Pending Liquidation',
            advanceAmount: 5000.00,
            overdueDays: 3,
            reference: 'SEED-OVD-EMP-002'
        );

        $this->command?->info('Successfully seeded overdue cash advances with overpayment for both employee and admin.');
    }

    private function seedOverdueAdvanceWithOverpayment(
        User $user,
        User $approver,
        string $purpose,
        float $advanceAmount,
        float $expenseAmount,
        int $overdueDays,
        string $reference,
        string $invoiceNumber,
        int $categoryId
    ): void {
        $today = Carbon::today();
        $dueDate = $today->copy()->subDays($overdueDays);
        $disbursedAt = $dueDate->copy()->subDays(10);
        $approvedAt = $disbursedAt->copy()->subDays(2);
        $submittedAt = $approvedAt->copy()->subDays(2);

        $advance = CashAdvance::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->first();

        if (!$advance) {
            $advance = CashAdvance::create([
                'user_id' => $user->id,
                'purpose' => $purpose,
                'amount' => $advanceAmount,
                'outstanding_balance' => $advanceAmount,
                'expected_disbursement_date' => $disbursedAt,
                'expected_liquidation_date' => $dueDate,
                'status' => 'overdue',
                'signature' => 'SEEDED-SIGNATURE-' . $user->id,
                'acknowledged_at' => $disbursedAt->copy()->addDay(),
            ]);

            $advance->forceFill([
                'created_at' => $submittedAt,
                'submitted_at' => $submittedAt,
            ])->save();
        } else {
            $advance->forceFill([
                'amount' => $advanceAmount,
                'outstanding_balance' => $advanceAmount,
                'expected_disbursement_date' => $disbursedAt,
                'expected_liquidation_date' => $dueDate,
                'status' => 'overdue',
                'acknowledged_at' => $disbursedAt->copy()->addDay(),
            ])->save();
        }

        // Approval Action
        CashAdvanceApprovalAction::updateOrCreate(
            ['cash_advance_id' => $advance->id],
            [
                'approver_id' => $approver->id,
                'action' => 'approved',
                'comment' => 'Approved for client travel.',
                'actioned_at' => $approvedAt,
            ]
        );

        // Disbursement
        CashAdvanceDisbursement::updateOrCreate(
            ['cash_advance_id' => $advance->id],
            [
                'disbursed_by_id' => $approver->id,
                'disbursement_date' => $disbursedAt,
                'channel' => 'bank-transfer',
                'reference_number' => $reference,
            ]
        );

        // Status history
        $advance->statusHistory()->delete();
        $transitions = [
            ['from' => null, 'to' => 'pending', 'at' => $submittedAt, 'by' => $user->id],
            ['from' => 'pending', 'to' => 'approved', 'at' => $approvedAt, 'by' => $approver->id],
            ['from' => 'approved', 'to' => 'disbursed', 'at' => $disbursedAt, 'by' => $approver->id],
            ['from' => 'disbursed', 'to' => 'signed', 'at' => $disbursedAt->copy()->addDay(), 'by' => $user->id],
            ['from' => 'signed', 'to' => 'overdue', 'at' => $dueDate->copy()->addDay(), 'by' => $approver->id],
        ];

        foreach ($transitions as $t) {
            CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => $t['from'],
                'to_status' => $t['to'],
                'changed_by' => $t['by'],
                'changed_at' => $t['at'],
            ]);
        }

        // Penalty record
        PenaltyRecord::where('cash_advance_id', $advance->id)->delete();
        PenaltyRecord::create([
            'cash_advance_id' => $advance->id,
            'days_overdue' => $overdueDays,
            'penalty_amount' => $overdueDays * 50.00,
        ]);

        // Receipt with excess expenses to create overpayment
        $receipt = Receipt::where('invoice_number', $invoiceNumber)->first();
        if (!$receipt) {
            $path = 'receipts/mock_overdue_receipt.png';
            $hash = hash('sha256', implode('|', [$path, $user->id, $invoiceNumber, $expenseAmount]));
            $receipt = Receipt::create([
                'uploaded_by' => $user->id,
                'file_path' => [$path],
                'file_hash' => [$hash],
                'file_type' => ['png'],
                'file_size_bytes' => [150000],
                'vendor_name' => 'Metro Premier Services & Supplies',
                'transaction_date' => $disbursedAt->copy()->addDays(2)->toDateString(),
                'total_amount' => $expenseAmount,
                'vat_amount' => round($expenseAmount * 0.12 / 1.12, 2),
                'tin' => '987-654-321-000',
                'invoice_number' => $invoiceNumber,
                'vat_classification' => 'vat',
                'currency' => 'PHP',
                'location' => 'Makati City',
                'ocr_confidence_score' => 98.00,
                'ocr_flagged' => false,
                'is_archived' => false,
                'expense_category_id' => $categoryId,
                'status' => 'approved',
            ]);

            ReceiptItem::create([
                'receipt_id' => $receipt->id,
                'name' => 'Onsite Field Expenses & Project Supplies',
                'quantity' => 1,
                'price' => $expenseAmount,
            ]);
        }

        // Liquidation pointing to advance with excess amount
        $liquidation = Liquidation::where('cash_advance_id', $advance->id)->first();
        if ($liquidation) {
            $liquidation->update([
                'status' => 'pending',
                'reimbursement_ids' => [$receipt->id],
                'total_expense_amount' => $expenseAmount,
                'outstanding_balance' => $advanceAmount,
            ]);
        } else {
            Liquidation::create([
                'cash_advance_id' => $advance->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'reimbursement_ids' => [$receipt->id],
                'total_expense_amount' => $expenseAmount,
                'outstanding_balance' => $advanceAmount,
                'shortfall_explanation' => null,
                'admin_note' => null,
            ]);
        }
    }

    private function seedStandardOverdueAdvance(
        User $user,
        User $approver,
        string $purpose,
        float $advanceAmount,
        int $overdueDays,
        string $reference
    ): void {
        $today = Carbon::today();
        $dueDate = $today->copy()->subDays($overdueDays);
        $disbursedAt = $dueDate->copy()->subDays(10);
        $approvedAt = $disbursedAt->copy()->subDays(2);
        $submittedAt = $approvedAt->copy()->subDays(2);

        $advance = CashAdvance::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->first();

        if (!$advance) {
            $advance = CashAdvance::create([
                'user_id' => $user->id,
                'purpose' => $purpose,
                'amount' => $advanceAmount,
                'outstanding_balance' => $advanceAmount,
                'expected_disbursement_date' => $disbursedAt,
                'expected_liquidation_date' => $dueDate,
                'status' => 'overdue',
                'signature' => 'SEEDED-SIGNATURE-' . $user->id,
                'acknowledged_at' => $disbursedAt->copy()->addDay(),
            ]);

            $advance->forceFill([
                'created_at' => $submittedAt,
                'submitted_at' => $submittedAt,
            ])->save();
        } else {
            $advance->forceFill([
                'amount' => $advanceAmount,
                'outstanding_balance' => $advanceAmount,
                'expected_disbursement_date' => $disbursedAt,
                'expected_liquidation_date' => $dueDate,
                'status' => 'overdue',
                'acknowledged_at' => $disbursedAt->copy()->addDay(),
            ])->save();
        }

        CashAdvanceApprovalAction::updateOrCreate(
            ['cash_advance_id' => $advance->id],
            [
                'approver_id' => $approver->id,
                'action' => 'approved',
                'comment' => 'Approved for maintenance.',
                'actioned_at' => $approvedAt,
            ]
        );

        CashAdvanceDisbursement::updateOrCreate(
            ['cash_advance_id' => $advance->id],
            [
                'disbursed_by_id' => $approver->id,
                'disbursement_date' => $disbursedAt,
                'channel' => 'bank-transfer',
                'reference_number' => $reference,
            ]
        );

        $advance->statusHistory()->delete();
        $transitions = [
            ['from' => null, 'to' => 'pending', 'at' => $submittedAt, 'by' => $user->id],
            ['from' => 'pending', 'to' => 'approved', 'at' => $approvedAt, 'by' => $approver->id],
            ['from' => 'approved', 'to' => 'disbursed', 'at' => $disbursedAt, 'by' => $approver->id],
            ['from' => 'disbursed', 'to' => 'signed', 'at' => $disbursedAt->copy()->addDay(), 'by' => $user->id],
            ['from' => 'signed', 'to' => 'overdue', 'at' => $dueDate->copy()->addDay(), 'by' => $approver->id],
        ];

        foreach ($transitions as $t) {
            CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => $t['from'],
                'to_status' => $t['to'],
                'changed_by' => $t['by'],
                'changed_at' => $t['at'],
            ]);
        }

        PenaltyRecord::where('cash_advance_id', $advance->id)->delete();
        PenaltyRecord::create([
            'cash_advance_id' => $advance->id,
            'days_overdue' => $overdueDays,
            'penalty_amount' => $overdueDays * 50.00,
        ]);
    }
}
