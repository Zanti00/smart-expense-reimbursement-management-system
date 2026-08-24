<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Users\Models\User;
use App\Modules\CashAdvances\Models\CashAdvance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

/**
 * Regression coverage for Cash Advance password-bypass fix.
 *
 * Bug: admin could request revision (reject/approve/disburse) with WRONG password
 * and still increment revision_count / change status.
 *
 * Fix: Controller now requires password (required|string) on Approve/Reject/Disburse.
 * Service verifies via PasswordVerificationService::verify BEFORE any status increment
 * inside DB::transaction. Invalid password => ValidationException => 422 errors.password,
 * transaction never commits, no side effects.
 *
 * Mock strategy: mirrors ReimbursementPasswordVerificationTest.php â€” Http::fake the
 * external capstone_auth /api/verify-password endpoint.
 *   - valid true  => correct password (seeded password = "password")
 *   - valid false => wrong password
 *
 * This avoids coupling to live auth service and keeps tests deterministic.
 */
class CashAdvancePasswordVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $approver;
    private CashAdvance $cashAdvance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::create([
            "name" => "John Employee",
            "email" => "john.employee@example.com",
            "role" => "employee",
            "grade" => "L2",
            "department" => "Sales",
        ]);

        $this->approver = User::create([
            "name" => "Approver Admin",
            "email" => "approver@example.com",
            "role" => "admin",
            "grade" => "L4",
            "department" => "Accounting",
        ]);

        $this->cashAdvance = CashAdvance::create([
            "user_id" => $this->employee->id,
            "purpose" => "Business travel to Cebu",
            "amount" => 5000.00,
            "status" => "pending",
            "revision_count" => 0,
            "expected_disbursement_date" => Carbon::now()->addDays(2)->toDateString(),
            "expected_liquidation_date" => Carbon::now()->addDays(7)->toDateString(),
        ]);
    }

    private function approverToken(array $overrides = []): string
    {
        return $this->generateMockToken(array_merge([
            "email" => $this->approver->email,
            "role" => $this->approver->role,
            "department" => "Accounting",
            "first_name" => "Approver",
            "last_name" => "Admin",
        ], $overrides));
    }

    private function employeeToken(array $overrides = []): string
    {
        return $this->generateMockToken(array_merge([
            "email" => $this->employee->email,
            "role" => $this->employee->role,
            "department" => "Sales",
            "first_name" => "John",
            "last_name" => "Employee",
        ], $overrides));
    }

    private function makeCashAdvance(array $overrides = []): CashAdvance
    {
        return CashAdvance::create(array_merge([
            "user_id" => $this->employee->id,
            "purpose" => "Test advance purpose",
            "amount" => 3000.00,
            "status" => "pending",
            "revision_count" => 0,
            "expected_disbursement_date" => Carbon::now()->addDays(2)->toDateString(),
            "expected_liquidation_date" => Carbon::now()->addDays(7)->toDateString(),
        ], $overrides));
    }

    // =================================================================
    //  a) reject with correct password succeeds (200) increments revision_count
    // =================================================================
    public function test_reject_with_correct_password_succeeds_increments_revision_count_and_status_revise(): void
    {
        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => true], 200),
        ]);

        $token = $this->approverToken();

        $response = $this->withHeaders([
            "Authorization" => "Bearer " . $token,
        ])->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
            "comment" => "Please revise your receipts, missing VAT breakdown.",
            "action" => "revise",
            "password" => "password", // correct seeded password
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath("data.status", "revise");

        $this->cashAdvance->refresh();
        $this->assertEquals("revise", $this->cashAdvance->status);
        $this->assertEquals(1, (int) $this->cashAdvance->revision_count);

        $this->assertDatabaseHas("cash_advance_approval_actions", [
            "cash_advance_id" => $this->cashAdvance->id,
            "action" => "revised",
            "comment" => "Please revise your receipts, missing VAT breakdown.",
        ]);

        $this->assertDatabaseHas("cash_advance_status_history", [
            "cash_advance_id" => $this->cashAdvance->id,
            "from_status" => "pending",
            "to_status" => "revise",
        ]);
    }

    // =================================================================
    //  b) reject with WRONG password returns 422, NO side effects
    // =================================================================
    public function test_reject_with_wrong_password_returns_422_no_increment_no_status_change_no_action(): void
    {
        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => false], 200),
        ]);

        $token = $this->approverToken();

        $response = $this->withHeaders([
            "Authorization" => "Bearer " . $token,
        ])->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
            "comment" => "Please revise your receipts, missing VAT breakdown.",
            "action" => "revise",
            "password" => "wrong-password",
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["password"]);

        $this->cashAdvance->refresh();
        $this->assertEquals("pending", $this->cashAdvance->status, "Status must stay pending on wrong password");
        $this->assertEquals(0, (int) $this->cashAdvance->revision_count, "revision_count must NOT increment on wrong password");

        $this->assertDatabaseCount("cash_advance_approval_actions", 0);
        $this->assertDatabaseCount("cash_advance_status_history", 0);
    }

    // =================================================================
    //  c) reject with missing password returns 422 validation
    // =================================================================
    public function test_reject_with_missing_password_returns_422_validation(): void
    {
        // No Http::fake needed â€” FormRequest fails before service is called.
        // Still fake to ensure no external call leaks if validation ever bypassed.
        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => true], 200),
        ]);

        $token = $this->approverToken();

        $response = $this->withHeaders([
            "Authorization" => "Bearer " . $token,
        ])->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
            "comment" => "Missing password field entirely.",
            "action" => "revise",
            // password intentionally omitted
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["password"]);

        $this->cashAdvance->refresh();
        $this->assertEquals("pending", $this->cashAdvance->status);
        $this->assertEquals(0, (int) $this->cashAdvance->revision_count);
        $this->assertDatabaseCount("cash_advance_approval_actions", 0);
    }

    public function test_reject_with_empty_string_password_returns_422(): void
    {
        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => true], 200),
        ]);

        $token = $this->approverToken();

        $response = $this->withHeaders([
            "Authorization" => "Bearer " . $token,
        ])->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
            "comment" => "Empty password string.",
            "action" => "revise",
            "password" => "",
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["password"]);
    }

    // =================================================================
    //  d) approve with wrong password 422 no change, correct 200 approved
    // =================================================================
    public function test_approve_with_wrong_password_returns_422_no_status_change(): void
    {
        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => false], 200),
        ]);

        $token = $this->approverToken();

        $response = $this->withHeaders([
            "Authorization" => "Bearer " . $token,
        ])->postJson("/api/cash-advances/{$this->cashAdvance->id}/approve", [
            "comment" => "Attempt to approve with wrong password.",
            "password" => "wrong-password",
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["password"]);

        $this->cashAdvance->refresh();
        $this->assertEquals("pending", $this->cashAdvance->status);
        $this->assertDatabaseCount("cash_advance_approval_actions", 0);
        $this->assertDatabaseCount("cash_advance_status_history", 0);
    }

    public function test_approve_with_correct_password_succeeds_status_approved(): void
    {
        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => true], 200),
        ]);

        $token = $this->approverToken();

        $response = $this->withHeaders([
            "Authorization" => "Bearer " . $token,
        ])->postJson("/api/cash-advances/{$this->cashAdvance->id}/approve", [
            "comment" => "Looks good, approved.",
            "password" => "password",
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath("data.status", "approved");

        $this->cashAdvance->refresh();
        $this->assertEquals("approved", $this->cashAdvance->status);

        $this->assertDatabaseHas("cash_advance_approval_actions", [
            "cash_advance_id" => $this->cashAdvance->id,
            "action" => "approved",
        ]);

        $this->assertDatabaseHas("cash_advance_status_history", [
            "cash_advance_id" => $this->cashAdvance->id,
            "from_status" => "pending",
            "to_status" => "approved",
        ]);
    }

    public function test_approve_with_missing_password_returns_422(): void
    {
        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => true], 200),
        ]);

        $token = $this->approverToken();

        $response = $this->withHeaders([
            "Authorization" => "Bearer " . $token,
        ])->postJson("/api/cash-advances/{$this->cashAdvance->id}/approve", [
            "comment" => "Missing password on approve.",
            // password omitted
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["password"]);

        $this->cashAdvance->refresh();
        $this->assertEquals("pending", $this->cashAdvance->status);
    }

    // =================================================================
    //  e) disburse with wrong password 422 no change, correct 200 disbursed
    // =================================================================
    public function test_disburse_with_wrong_password_returns_422_no_status_change(): void
    {
        $approved = $this->makeCashAdvance(["status" => "approved"]);

        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => false], 200),
        ]);

        $token = $this->approverToken();

        $response = $this->withHeaders([
            "Authorization" => "Bearer " . $token,
        ])->postJson("/api/cash-advances/{$approved->id}/disburse", [
            "channel" => "bank_transfer",
            "reference" => "REF-12345",
            "password" => "wrong-password",
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["password"]);

        $approved->refresh();
        $this->assertEquals("approved", $approved->status);
        $this->assertDatabaseCount("cash_advance_disbursements", 0);
        $this->assertDatabaseMissing("cash_advance_status_history", [
            "cash_advance_id" => $approved->id,
            "to_status" => "disbursed",
        ]);
    }

    public function test_disburse_with_correct_password_succeeds_disbursed(): void
    {
        $approved = $this->makeCashAdvance(["status" => "approved"]);

        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => true], 200),
        ]);

        $token = $this->approverToken();

        $response = $this->withHeaders([
            "Authorization" => "Bearer " . $token,
        ])->postJson("/api/cash-advances/{$approved->id}/disburse", [
            "channel" => "bank_transfer",
            "reference" => "REF-67890",
            "password" => "password",
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath("data.status", "disbursed");

        $approved->refresh();
        $this->assertEquals("disbursed", $approved->status);
        $this->assertEquals((float) $approved->amount, (float) $approved->outstanding_balance);

        $this->assertDatabaseHas("cash_advance_disbursements", [
            "cash_advance_id" => $approved->id,
            "channel" => "bank_transfer",
            "reference_number" => "REF-67890",
        ]);

        $this->assertDatabaseHas("cash_advance_status_history", [
            "cash_advance_id" => $approved->id,
            "from_status" => "approved",
            "to_status" => "disbursed",
        ]);
    }

    public function test_disburse_with_missing_password_returns_422(): void
    {
        $approved = $this->makeCashAdvance(["status" => "approved"]);

        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => true], 200),
        ]);

        $token = $this->approverToken();

        $response = $this->withHeaders([
            "Authorization" => "Bearer " . $token,
        ])->postJson("/api/cash-advances/{$approved->id}/disburse", [
            "channel" => "bank_transfer",
            "reference" => "REF-MISSING-PW",
            // password omitted
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["password"]);

        $approved->refresh();
        $this->assertEquals("approved", $approved->status);
        $this->assertDatabaseCount("cash_advance_disbursements", 0);
    }

    // =================================================================
    //  f) wrong password does NOT increment even on 2nd/3rd attempt
    //     (idempotent guard BEFORE increment inside transaction)
    // =================================================================
    public function test_wrong_password_does_not_increment_on_repeated_attempts(): void
    {
        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => false], 200),
        ]);

        $token = $this->approverToken();

        for ($i = 1; $i <= 3; $i++) {
            $response = $this->withHeaders([
                "Authorization" => "Bearer " . $token,
            ])->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Wrong password attempt {$i} should not increment.",
                "action" => "revise",
                "password" => "wrong-password-{$i}",
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(["password"]);
        }

        $this->cashAdvance->refresh();
        $this->assertEquals("pending", $this->cashAdvance->status);
        $this->assertEquals(0, (int) $this->cashAdvance->revision_count);
        $this->assertDatabaseCount("cash_advance_approval_actions", 0);
        $this->assertDatabaseCount("cash_advance_status_history", 0);
    }

    public function test_wrong_password_after_successful_revise_does_not_increment_further(): void
    {
        $token = $this->approverToken();

        // First: successful revise with correct password (count => 1)
        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => true], 200),
        ]);

        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "First correct revise.",
                "action" => "revise",
                "password" => "password",
            ])->assertStatus(200);

        $this->cashAdvance->refresh();
        $this->assertEquals("revise", $this->cashAdvance->status);
        $this->assertEquals(1, (int) $this->cashAdvance->revision_count);
        $this->assertDatabaseCount("cash_advance_approval_actions", 1);

        // Second: wrong password while still in revise (should be 422, count stays 1)
        Http::fake([
            "*/api/verify-password" => Http::response(["valid" => false], 200),
        ]);

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Wrong password after one successful revise.",
                "action" => "revise",
                "password" => "wrong-password",
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["password"]);

        $this->cashAdvance->refresh();
        $this->assertEquals("revise", $this->cashAdvance->status);
        $this->assertEquals(1, (int) $this->cashAdvance->revision_count);
        $this->assertDatabaseCount("cash_advance_approval_actions", 1, "No new approval_action on wrong password");

        // Third: another wrong attempt still 1
        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Second wrong attempt still should not increment.",
                "action" => "revise",
                "password" => "another-wrong",
            ]);

        $response->assertStatus(422);
        $this->cashAdvance->refresh();
        $this->assertEquals(1, (int) $this->cashAdvance->revision_count);
    }

    public function test_wrong_password_after_second_successful_revise_still_guarded(): void
    {
        $token = $this->approverToken();

        // Seed 2 successful revises (pending->revise->pending->revise)
        Http::fake(["*/api/verify-password" => Http::response(["valid" => true], 200)]);

        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Seed revise 1.",
                "action" => "revise",
                "password" => "password",
            ])->assertStatus(200);

        $this->cashAdvance->refresh();
        $this->cashAdvance->update(["status" => "pending"]);

        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Seed revise 2.",
                "action" => "revise",
                "password" => "password",
            ])->assertStatus(200);

        $this->cashAdvance->refresh();
        $this->assertEquals(2, (int) $this->cashAdvance->revision_count);

        // Now wrong password must not push to 3
        Http::fake(["*/api/verify-password" => Http::response(["valid" => false], 200)]);

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Wrong password at count 2 must not become 3.",
                "action" => "revise",
                "password" => "wrong-password",
            ]);

        $response->assertStatus(422);
        $this->cashAdvance->refresh();
        $this->assertEquals(2, (int) $this->cashAdvance->revision_count);
        $this->assertDatabaseCount("cash_advance_approval_actions", 2);
    }

    // =================================================================
    //  g) validation: password required on approve/reject/disburse
    //     (FormRequest required|string => 422 when missing/empty)
    // =================================================================
    public function test_validation_password_required_on_all_protected_endpoints(): void
    {
        $token = $this->approverToken();
        $approved = $this->makeCashAdvance(["status" => "approved"]);

        Http::fake(["*/api/verify-password" => Http::response(["valid" => true], 200)]);

        // Approve without password
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/approve", [
                "comment" => "No password supplied.",
            ])->assertStatus(422)->assertJsonValidationErrors(["password"]);

        // Reject without password (pending)
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$approved->id}/reject", [
                "comment" => "No password on reject.",
                "action" => "revise",
            ]);

        // Use a pending record for reject missing password
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "No password on reject pending.",
                "action" => "revise",
            ])->assertStatus(422)->assertJsonValidationErrors(["password"]);

        // Disburse without password
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$approved->id}/disburse", [
                "channel" => "bank_transfer",
                "reference" => "REF-NO-PW",
            ])->assertStatus(422)->assertJsonValidationErrors(["password"]);
    }

    public function test_validation_password_must_be_string(): void
    {
        Http::fake(["*/api/verify-password" => Http::response(["valid" => true], 200)]);
        $token = $this->approverToken();

        // Sending password as integer should fail string validation
        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Password as integer.",
                "action" => "revise",
                "password" => 12345,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["password"]);
    }

    // =================================================================
    //  Extra: wrong password does not mask RBAC 403 or 409, and correct
    //  password after 409 still 409 (status guard before password in controller
    //  vs inside transaction). We assert password bypass is the critical path.
    // =================================================================
    public function test_correct_password_still_returns_409_when_status_not_pending_on_approve(): void
    {
        // Seed an approved record then try to approve again with correct password => 409
        $approved = $this->makeCashAdvance(["status" => "approved"]);

        Http::fake(["*/api/verify-password" => Http::response(["valid" => true], 200)]);
        $token = $this->approverToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$approved->id}/approve", [
                "comment" => "Second approve should conflict.",
                "password" => "password",
            ]);

        $response->assertStatus(409);
    }

    public function test_correct_password_still_returns_409_when_reject_on_approved(): void
    {
        $approved = $this->makeCashAdvance(["status" => "approved"]);

        Http::fake(["*/api/verify-password" => Http::response(["valid" => true], 200)]);
        $token = $this->approverToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$approved->id}/reject", [
                "comment" => "Reject on approved should conflict.",
                "action" => "revise",
                "password" => "password",
            ]);

        $response->assertStatus(409);
    }
}
