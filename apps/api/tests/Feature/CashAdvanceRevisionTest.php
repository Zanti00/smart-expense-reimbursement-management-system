<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Users\Models\User;
use App\Modules\CashAdvances\Models\CashAdvance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class CashAdvanceRevisionTest extends TestCase
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

    // -----------------------------------------------------------------
    //  a) 1st revise succeeds with action="revise" (original 500 now 200)
    // -----------------------------------------------------------------
    public function test_first_revise_succeeds_with_action_revised(): void
    {
        $token = $this->approverToken();

        $response = $this->withHeaders([
            "Authorization" => "Bearer " . $token,
        ])->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
            "comment" => "Please revise your receipts, missing VAT breakdown.",
            "action" => "revise",
        ]);

        // Regression: before fix this returned 500 (Data truncated for column "action" enum)
        // After migration adding "revised" to enum it must be 200
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

    // -----------------------------------------------------------------
    //  b) 2nd & 3rd revise still revered (status remains revise)
    // -----------------------------------------------------------------
    public function test_second_and_third_revise_still_revised(): void
    {
        $token = $this->approverToken();

        // 1st revise
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "First revise - receipts incomplete.",
                "action" => "revise",
            ])->assertStatus(200);

        // Simulate employee re-submit: revise -> pending (required for realistic flow)
        // The service allows direct pending/revise -> revise without re-submit, but we test realistic reset
        $this->cashAdvance->refresh();
        $this->assertEquals("revise", $this->cashAdvance->status);
        // Employee updates to reset to pending
        $this->cashAdvance->update(["status" => "pending"]);

        // 2nd revise
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Second revise - still missing docs.",
                "action" => "revise",
            ])->assertStatus(200);

        $this->cashAdvance->refresh();
        $this->assertEquals("revise", $this->cashAdvance->status);
        $this->assertEquals(2, (int) $this->cashAdvance->revision_count);
        $this->assertDatabaseHas("cash_advance_approval_actions", [
            "cash_advance_id" => $this->cashAdvance->id,
            "action" => "revised",
            "comment" => "Second revise - still missing docs.",
        ]);

        // Reset again to pending for 3rd cycle
        $this->cashAdvance->update(["status" => "pending"]);

        // 3rd revise
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Third revise - final warning.",
                "action" => "revise",
            ])->assertStatus(200);

        $this->cashAdvance->refresh();
        $this->assertEquals("revise", $this->cashAdvance->status);
        $this->assertEquals(3, (int) $this->cashAdvance->revision_count);
        $this->assertDatabaseHas("cash_advance_approval_actions", [
            "cash_advance_id" => $this->cashAdvance->id,
            "action" => "revised",
            "comment" => "Third revise - final warning.",
        ]);

        // 3 approval actions total, all revised
        $this->assertDatabaseCount("cash_advance_approval_actions", 3);
    }

    public function test_consecutive_revises_without_employee_resubmit_still_revised(): void
    {
        $token = $this->approverToken();

        // Direct consecutive revises staying in "revise" status (allowed by controller pending|revise guard)
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
                ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                    "comment" => "Consecutive revise attempt {$i} needs correction.",
                    "action" => "revise",
                ]);
            $response->assertStatus(200);
            $this->cashAdvance->refresh();
            $this->assertEquals("revise", $this->cashAdvance->status);
            $this->assertEquals($i, (int) $this->cashAdvance->revision_count);
        }
    }

    // -----------------------------------------------------------------
    //  c) 4th revise escalates to rejected + next reject 409
    // -----------------------------------------------------------------
    public function test_fourth_revise_escalates_to_rejected_and_next_reject_conflicts(): void
    {
        $token = $this->approverToken();

        // Seed 3 prior revises
        for ($i = 1; $i <= 3; $i++) {
            $this->withHeaders(["Authorization" => "Bearer " . $token])
                ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                    "comment" => "Prior revise {$i} for escalation test.",
                    "action" => "revise",
                ])->assertStatus(200);

            // Reset to pending to mimic employee edit cycle, except after 3rd we can keep revise for 4th
            if ($i < 3) {
                $this->cashAdvance->refresh();
                $this->cashAdvance->update(["status" => "pending"]);
            }
        }

        $this->cashAdvance->refresh();
        $this->assertEquals("revise", $this->cashAdvance->status);
        $this->assertEquals(3, (int) $this->cashAdvance->revision_count);

        // 4th revise -> must escalate to rejected, forcing action=rejected regardless of input
        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Fourth strike - exceeded revision limit.",
                "action" => "revise",
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath("data.status", "rejected");

        $this->cashAdvance->refresh();
        $this->assertEquals("rejected", $this->cashAdvance->status);
        $this->assertEquals(4, (int) $this->cashAdvance->revision_count);

        $this->assertDatabaseHas("cash_advance_approval_actions", [
            "cash_advance_id" => $this->cashAdvance->id,
            "action" => "rejected",
            "comment" => "Fourth strike - exceeded revision limit.",
        ]);

        $this->assertDatabaseHas("cash_advance_status_history", [
            "cash_advance_id" => $this->cashAdvance->id,
            "to_status" => "rejected",
        ]);

        // Next reject must be 409 Conflict (not pending or revise)
        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Should conflict - already rejected.",
                "action" => "revise",
            ]);

        $response->assertStatus(409);
    }

    // -----------------------------------------------------------------
    //  d) direct reject action="reject" creates rejected approval action
    // -----------------------------------------------------------------
    public function test_direct_reject_action_reject_creates_rejected(): void
    {
        $token = $this->approverToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Direct rejection - violates policy.",
                "action" => "reject",
            ]);

        $response->assertStatus(200);
        // Even with direct reject, first strike keeps status revise but stores rejected action
        // Terminal escalation (count >3) always forces rejected status + action
        $this->cashAdvance->refresh();
        // For non-terminal direct reject: status is revise, approval action is rejected
        $this->assertEquals("revise", $this->cashAdvance->status);
        $this->assertEquals(1, (int) $this->cashAdvance->revision_count);

        $this->assertDatabaseHas("cash_advance_approval_actions", [
            "cash_advance_id" => $this->cashAdvance->id,
            "action" => "rejected",
            "comment" => "Direct rejection - violates policy.",
        ]);
    }

    public function test_direct_reject_on_fourth_strike_also_rejected(): void
    {
        $token = $this->approverToken();

        // Pre-seed 3 revises to reach cap
        for ($i = 1; $i <= 3; $i++) {
            $this->withHeaders(["Authorization" => "Bearer " . $token])
                ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                    "comment" => "Seed revise {$i} before direct reject.",
                    "action" => "revise",
                ])->assertStatus(200);
            if ($i < 3) {
                $this->cashAdvance->refresh();
                $this->cashAdvance->update(["status" => "pending"]);
            }
        }

        // 4th with explicit reject must also be rejected
        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Direct reject on 4th strike.",
                "action" => "reject",
            ]);

        $response->assertStatus(200);
        $this->cashAdvance->refresh();
        $this->assertEquals("rejected", $this->cashAdvance->status);
        $this->assertEquals(4, (int) $this->cashAdvance->revision_count);
        $this->assertDatabaseHas("cash_advance_approval_actions", [
            "cash_advance_id" => $this->cashAdvance->id,
            "action" => "rejected",
            "comment" => "Direct reject on 4th strike.",
        ]);
    }

    // -----------------------------------------------------------------
    //  e) approve still works
    // -----------------------------------------------------------------
    public function test_approve_still_works(): void
    {
        $token = $this->approverToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/approve", [
                "comment" => "Looks good, approved.",
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

    public function test_approve_after_revise_cycle(): void
    {
        $token = $this->approverToken();

        // Revise once then employee resets to pending then approve
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Need correction before approval.",
                "action" => "revise",
            ])->assertStatus(200);

        $this->cashAdvance->refresh();
        $this->cashAdvance->update(["status" => "pending"]);

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/approve", [
                "comment" => "Corrected, now approved.",
            ]);

        $response->assertStatus(200);
        $this->cashAdvance->refresh();
        $this->assertEquals("approved", $this->cashAdvance->status);
    }

    // -----------------------------------------------------------------
    //  f) validation: invalid actions, missing comment, short comment, defaults to revise
    // -----------------------------------------------------------------
    public function test_validation_rejects_invalid_action(): void
    {
        $token = $this->approverToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Valid comment length.",
                "action" => "invalid_action",
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["action"]);
    }

    public function test_validation_rejects_missing_comment(): void
    {
        $token = $this->approverToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "action" => "revise",
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["comment"]);
    }

    public function test_validation_rejects_short_comment(): void
    {
        $token = $this->approverToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "no",
                "action" => "revise",
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["comment"]);

        // Exactly 4 chars also fails (min:5)
        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "abcd",
                "action" => "revise",
            ]);

        $response->assertStatus(422);
    }

    public function test_validation_rejects_short_comment_on_reject_action(): void
    {
        $token = $this->approverToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "bad",
                "action" => "reject",
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["comment"]);
    }

    public function test_defaults_action_to_revise_when_omitted(): void
    {
        $token = $this->approverToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Omitted action should default to revise.",
                // action intentionally omitted
            ]);

        $response->assertStatus(200);
        $this->cashAdvance->refresh();
        $this->assertEquals("revise", $this->cashAdvance->status);
        $this->assertEquals(1, (int) $this->cashAdvance->revision_count);

        $this->assertDatabaseHas("cash_advance_approval_actions", [
            "cash_advance_id" => $this->cashAdvance->id,
            "action" => "revised",
            "comment" => "Omitted action should default to revise.",
        ]);
    }

    public function test_defaults_action_to_revise_on_second_omission(): void
    {
        $token = $this->approverToken();

        // First with omitted action
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "First omitted action default.",
            ])->assertStatus(200);

        $this->cashAdvance->refresh();
        $this->cashAdvance->update(["status" => "pending"]);

        // Second with omitted action still revised
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Second omitted action default.",
            ])->assertStatus(200);

        $this->cashAdvance->refresh();
        $this->assertEquals("revise", $this->cashAdvance->status);
        $this->assertEquals(2, (int) $this->cashAdvance->revision_count);
        $this->assertDatabaseHas("cash_advance_approval_actions", [
            "cash_advance_id" => $this->cashAdvance->id,
            "action" => "revised",
            "comment" => "Second omitted action default.",
        ]);
    }

    // -----------------------------------------------------------------
    //  RBAC 403 tests
    // -----------------------------------------------------------------
    public function test_rbac_employee_cannot_reject_returns_403(): void
    {
        $token = $this->employeeToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Employee trying to reject.",
                "action" => "revise",
            ]);

        $response->assertStatus(403);
    }

    public function test_rbac_employee_cannot_approve_returns_403(): void
    {
        $token = $this->employeeToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/approve", [
                "comment" => "Employee trying to approve.",
            ]);

        $response->assertStatus(403);
    }

    public function test_rbac_unauthenticated_returns_401(): void
    {
        $response = $this->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
            "comment" => "No token provided.",
            "action" => "revise",
        ]);

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------
    //  409 guard tests
    // -----------------------------------------------------------------
    public function test_409_guard_reject_on_approved_returns_conflict(): void
    {
        $approverToken = $this->approverToken();

        // Approve first
        $this->withHeaders(["Authorization" => "Bearer " . $approverToken])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/approve", [
                "comment" => "Approving for 409 test.",
            ])->assertStatus(200);

        $this->cashAdvance->refresh();
        $this->assertEquals("approved", $this->cashAdvance->status);

        // Try to reject approved -> 409
        $response = $this->withHeaders(["Authorization" => "Bearer " . $approverToken])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Rejecting approved should conflict.",
                "action" => "revise",
            ]);

        $response->assertStatus(409);
    }

    public function test_409_guard_reject_on_rejected_returns_conflict(): void
    {
        $token = $this->approverToken();

        // Force to rejected via 4 strikes
        $this->cashAdvance->update(["status" => "rejected", "revision_count" => 4]);

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Rejecting already rejected.",
                "action" => "revise",
            ]);

        $response->assertStatus(409);
    }

    public function test_409_guard_reject_on_disbursed_returns_conflict(): void
    {
        $disbursed = $this->makeCashAdvance(["status" => "disbursed"]);
        $token = $this->approverToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$disbursed->id}/reject", [
                "comment" => "Rejecting disbursed should conflict.",
                "action" => "revise",
            ]);

        $response->assertStatus(409);
    }

    public function test_409_guard_approve_on_non_pending_returns_conflict(): void
    {
        $token = $this->approverToken();

        // Approve once -> becomes approved
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/approve", [
                "comment" => "First approval.",
            ])->assertStatus(200);

        // Second approve on same record -> 409 (not pending)
        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/approve", [
                "comment" => "Second approval should conflict.",
            ]);

        $response->assertStatus(409);
    }

    public function test_409_guard_approve_on_revise_returns_conflict(): void
    {
        $token = $this->approverToken();

        // Move to revise
        $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/reject", [
                "comment" => "Moving to revise for guard test.",
                "action" => "revise",
            ])->assertStatus(200);

        $this->cashAdvance->refresh();
        $this->assertEquals("revise", $this->cashAdvance->status);

        // Approve revise -> 409 (controller only allows pending)
        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$this->cashAdvance->id}/approve", [
                "comment" => "Approve on revise should conflict.",
            ]);

        $response->assertStatus(409);
    }

    public function test_409_guard_approve_on_rejected_returns_conflict(): void
    {
        $rejected = $this->makeCashAdvance(["status" => "rejected", "revision_count" => 4]);
        $token = $this->approverToken();

        $response = $this->withHeaders(["Authorization" => "Bearer " . $token])
            ->postJson("/api/cash-advances/{$rejected->id}/approve", [
                "comment" => "Approve rejected should conflict.",
            ]);

        $response->assertStatus(409);
    }
}
