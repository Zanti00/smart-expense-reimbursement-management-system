<?php

namespace App\Modules\CashAdvances\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\CashAdvances\Services\CashAdvanceService;
use App\Modules\CashAdvances\Http\Requests\StoreCashAdvanceRequest;
use App\Modules\CashAdvances\Http\Requests\UpdateCashAdvanceRequest;
use App\Modules\CashAdvances\Http\Requests\ApproveCashAdvanceRequest;
use App\Modules\CashAdvances\Http\Requests\RejectCashAdvanceRequest;
use App\Modules\CashAdvances\Http\Requests\DisburseCashAdvanceRequest;
use App\Modules\CashAdvances\Http\Requests\AcknowledgeCashAdvanceRequest;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class CashAdvanceController extends Controller
{
    protected CashAdvanceService $service;

    public function __construct(CashAdvanceService $service)
    {
        $this->service = $service;
    }

    private const ADVANCE_WITH = [
        'requester',
        'approvalActions.approver',
        'statusHistory.changedBy',
        'statusHistory.user',
        'disbursement.disbursedBy',
        'penalties',
        'liquidations',
        'document',
    ];

    /**
     * Enrich a single CashAdvance model with Unified Roadmap computed fields.
     * Keeps SQL aggregate for penalties_total but falls back to collection sum if not eager-summed.
     */
    private function enrichAdvance(CashAdvance $advance): CashAdvance
    {
        // Ensure penalties_total via SQL withSum fallback to collection sum (no extra query if already loaded)
        $penaltiesTotal = $advance->getAttribute('penalties_total');
        if ($penaltiesTotal === null) {
            $penaltiesTotal = $advance->relationLoaded('penalties')
                ? $advance->penalties->sum(fn ($p) => (float) ($p->penalty_amount ?? 0))
                : (float) \App\Modules\Liquidations\Models\PenaltyRecord::where('cash_advance_id', $advance->id)->sum('penalty_amount');
        }
        $advance->setAttribute('penalties_total', (float) $penaltiesTotal);
        // snake alias for frontend that checks penalties_sum
        $advance->setAttribute('penalties_sum', (float) $penaltiesTotal);

        // days_overdue: flat logic mirrors liquidation.js calculateAging but server authoritative
        $due = $advance->expected_liquidation_date;
        $daysOverdue = 0;
        if ($due) {
            $today = Carbon::now()->startOfDay();
            $dueDate = Carbon::parse($due)->startOfDay();
            $isTerminal = in_array($advance->status, ['liquidated', 'settled', 'rejected']);
            $isAuditing = in_array($advance->status, ['pending', 'under-review', 'approved']);
            if ($today->gt($dueDate) && !$isTerminal && !$isAuditing) {
                $daysOverdue = $today->diffInDays($dueDate);
            } elseif ($advance->relationLoaded('penalties') && $advance->penalties->isNotEmpty()) {
                // If penalties exist, derive overdue days from count or max days_overdue
                $maxDays = $advance->penalties->max('days_overdue');
                if ($maxDays) {
                    $daysOverdue = max($daysOverdue, (int) $maxDays);
                }
            }
        } elseif ($advance->relationLoaded('penalties') && $advance->penalties->isNotEmpty()) {
            $daysOverdue = (int) ($advance->penalties->max('days_overdue') ?? $advance->penalties->count());
        }
        $advance->setAttribute('days_overdue', (int) $daysOverdue);

        // linked_liquidation / latest_liquidation / liquidation aliases (single latest)
        $liquidations = $advance->relationLoaded('liquidations') ? $advance->liquidations : collect();
        $latest = $liquidations->sortByDesc('created_at')->sortByDesc('id')->first();
        $advance->setAttribute('linked_liquidation', $latest);
        $advance->setAttribute('latest_liquidation', $latest);
        $advance->setAttribute('liquidation', $latest); // singular alias expected by cashAdvance.js normalize
        // also expose liquidations collection as-is for direct access

        // Unified Roadmap overpayment: when latest liquidation expense exceeds outstanding at time
        $overpayment = 0;
        if ($latest) {
            $expense = (float) ($latest->total_expense_amount ?? 0);
            $snapshotBalance = (float) ($latest->outstanding_balance ?? $advance->outstanding_balance ?? $advance->amount ?? 0);
            // If expense > snapshot, the excess is overpayment forwarded to reimbursement
            if ($expense > $snapshotBalance) {
                $overpayment = $expense - $snapshotBalance;
            }
        }
        $advance->setAttribute('overpayment_amount', (float) $overpayment);
        $advance->setAttribute('overpaymentAmount', (float) $overpayment);

        // Normalized status_history array for UnifiedRoadmapStepper (with dates + actor)
        $history = collect();
        if ($advance->relationLoaded('statusHistory')) {
            $history = $advance->statusHistory->sortBy('changed_at')->sortBy('id')->map(function ($h) {
                $actor = $h->relationLoaded('changedBy') && $h->changedBy ? $h->changedBy : ($h->relationLoaded('user') ? $h->user : null);
                // Fallback to direct load if relation not eager but we have with()
                return [
                    'id' => $h->id,
                    'cash_advance_id' => $h->cash_advance_id,
                    'from_status' => $h->from_status,
                    'to_status' => $h->to_status,
                    'status' => $h->to_status,
                    'changed_by' => $actor ? ['id' => $actor->id, 'name' => $actor->name, 'email' => $actor->email] : null,
                    'changed_by_id' => $h->changed_by,
                    'actor' => $actor?->name,
                    'user' => $actor ? ['id' => $actor->id, 'name' => $actor->name, 'email' => $actor->email] : null,
                    'changed_at' => $h->changed_at ?? $h->created_at,
                    'created_at' => $h->created_at,
                    'updated_at' => $h->updated_at,
                ];
            })->values();
        }
        $advance->setAttribute('status_history', $history);
        // Also merge approvalActions into history for frontends that check approval_actions
        $approvalHistory = collect();
        if ($advance->relationLoaded('approvalActions')) {
            $approvalHistory = $advance->approvalActions->map(function ($a) {
                $approver = $a->relationLoaded('approver') ? $a->approver : null;
                return [
                    'id' => $a->id,
                    'action' => $a->action,
                    'comment' => $a->comment,
                    'approver' => $approver ? ['id' => $approver->id, 'name' => $approver->name, 'email' => $approver->email] : null,
                    'created_at' => $a->created_at,
                    'actioned_at' => $a->actioned_at ?? $a->created_at,
                ];
            });
        }
        $advance->setAttribute('approval_actions', $approvalHistory);
        $advance->setAttribute('approvalActions', $approvalHistory);

        // Ensure aging-ready fields are always present (casts preserve null but we ensure key exists)
        $advance->setAttribute('outstanding_balance', $advance->outstanding_balance);
        $advance->setAttribute('expected_liquidation_date', $advance->expected_liquidation_date);
        $advance->setAttribute('expected_disbursement_date', $advance->expected_disbursement_date);
        // Alias for cashAdvance.js fallback
        $advance->setAttribute('balance', $advance->outstanding_balance !== null ? (float) $advance->outstanding_balance : (float) ($advance->amount ?? 0));

        return $advance;
    }

    /**
     * List all cash advances.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', CashAdvance::class);

        $user = $request->user();

        $query = CashAdvance::with(self::ADVANCE_WITH)
            ->withSum('penalties as penalties_total', 'penalty_amount')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (!$user->can('serms.cash_advances.manage')) {
            $query->where('user_id', $user->id);
        }

        $advances = $query->get()->map(fn (CashAdvance $a) => $this->enrichAdvance($a));

        return response()->json($advances);
    }

    /**
     * Request a new cash advance.
     */
    public function store(StoreCashAdvanceRequest $request)
    {
        Gate::authorize('create', CashAdvance::class);

        $advance = $this->service->createAdvance(
            $request->user(),
            $request->validated(),
            $request->file('documents', [])
        );

        $advance->load(self::ADVANCE_WITH);
        $advance->loadSum('penalties as penalties_total', 'penalty_amount');
        $this->enrichAdvance($advance);

        return response()->json([
            'message' => 'Cash advance request created successfully.',
            'data' => $advance,
        ], 201);
    }

    /**
     * View detailed cash advance.
     */
    public function show(Request $request, $id)
    {
        $advance = CashAdvance::with(self::ADVANCE_WITH)
            ->withSum('penalties as penalties_total', 'penalty_amount')
            ->findOrFail($id);

        Gate::authorize('view', $advance);

        $this->enrichAdvance($advance);

        return response()->json($advance);
    }

    /**
     * Get the document for a cash advance.
     */
    public function document(Request $request, $id)
    {
        $advance = CashAdvance::findOrFail($id);

        Gate::authorize('view', $advance);

        return response()->json($advance->document);
    }

    /**
     * Approve a cash advance.
     */
    public function approve(ApproveCashAdvanceRequest $request, $id)
    {
        $advance = CashAdvance::findOrFail($id);

        Gate::authorize('approve', $advance);

        if ($advance->status !== 'pending') {
            return response()->json(['message' => 'Conflict. Cash advance is not pending.'], 409);
        }

        try {
            $advance = $this->service->approveAdvance(
                $advance,
                $request->user(),
                $request->validated('comment'),
                $request->validated('password'),
                $request
            );

            $advance->load(self::ADVANCE_WITH);
            $advance->loadSum('penalties as penalties_total', 'penalty_amount');
            $this->enrichAdvance($advance);

            return response()->json([
                'message' => 'Cash advance approved successfully.',
                'data' => $advance,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Reject a cash advance.
     */
    public function reject(RejectCashAdvanceRequest $request, $id)
    {
        $advance = CashAdvance::findOrFail($id);

        Gate::authorize('reject', $advance);

        if (!in_array($advance->status, ['pending', 'revise'])) {
            return response()->json(['message' => 'Conflict. Cash advance is not pending or revise.'], 409);
        }

        try {
            $advance = $this->service->rejectAdvance(
                $advance,
                $request->user(),
                $request->validated('comment'),
                $request->validated('action', 'revise'),
                $request->validated('password'),
                $request
            );

            $isRejected = $advance->status === 'rejected';
            $advance->load(self::ADVANCE_WITH);
            $advance->loadSum('penalties as penalties_total', 'penalty_amount');
            $this->enrichAdvance($advance);

            return response()->json([
                'message' => $isRejected ? 'Cash advance rejected (exceeded revision limit).' : 'Cash advance returned for revision.',
                'data' => $advance,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Disburse an approved cash advance.
     */
    public function disburse(DisburseCashAdvanceRequest $request, $id)
    {
        $advance = CashAdvance::findOrFail($id);

        Gate::authorize('disburse', $advance);

        if ($advance->status !== 'approved') {
            return response()->json(['message' => 'Conflict. Cash advance is not in an approved state.'], 409);
        }

        try {
            $validated = $request->validated();
            $password = $validated['password'];
            unset($validated['password']);

            $advance = $this->service->disburseAdvance(
                $advance,
                $request->user(),
                $validated,
                $password,
                $request
            );

            $advance->load(self::ADVANCE_WITH);
            $advance->loadSum('penalties as penalties_total', 'penalty_amount');
            $this->enrichAdvance($advance);

            return response()->json([
                'message' => 'Cash advance disbursed successfully.',
                'data' => $advance,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Acknowledge cash advance receipt.
     */
    public function acknowledge(AcknowledgeCashAdvanceRequest $request, $id)
    {
        $advance = CashAdvance::findOrFail($id);

        Gate::authorize('acknowledge', $advance);

        if (!in_array($advance->status, ['approved', 'disbursed'])) {
            return response()->json(['message' => 'Conflict. Cash advance is not in a valid state for acknowledgement.'], 409);
        }

        if ($advance->acknowledged_at !== null) {
            return response()->json(['message' => 'Conflict. Cash advance has already been acknowledged.'], 409);
        }

        $advance = $this->service->acknowledgeAdvance(
            $advance,
            $request->validated()
        );

        $advance->load(self::ADVANCE_WITH);
        $advance->loadSum('penalties as penalties_total', 'penalty_amount');
        $this->enrichAdvance($advance);

        return response()->json([
            'message' => 'Cash advance acknowledged successfully.',
            'data' => $advance,
        ]);
    }

    /**
     * Update a pending or revise cash advance (employee self-edit).
     */
    public function update(UpdateCashAdvanceRequest $request, $id)
    {
        $advance = CashAdvance::findOrFail($id);

        // Only the owner can edit
        if ($advance->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden. You do not own this cash advance.'], 403);
        }

        if ($advance->status === 'rejected') {
            return response()->json(['message' => 'Rejected cash advances (exceeded revision limit) cannot be edited.'], 409);
        }

        if (!in_array($advance->status, ['pending', 'revise'])) {
            return response()->json(['message' => 'Only pending or revise cash advances can be edited.'], 409);
        }

        $advance = $this->service->updateAdvance(
            $advance,
            $request->user(),
            $request->validated(),
            $request->file('documents', [])
        );

        $advance->load(self::ADVANCE_WITH);
        $advance->loadSum('penalties as penalties_total', 'penalty_amount');
        $this->enrichAdvance($advance);

        return response()->json([
            'message' => 'Cash advance updated successfully.',
            'data' => $advance,
        ]);
    }

    /**
     * Delete a pending cash advance request.
     */
    public function destroy(Request $request, $id)
    {
        $advance = CashAdvance::findOrFail($id);

        // Only the owner can delete
        if ($advance->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden. You do not own this cash advance.'], 403);
        }

        if ($advance->status !== 'pending') {
            return response()->json(['message' => 'Only pending cash advances can be deleted.'], 409);
        }

        try {
            $this->service->deleteAdvance(
                $advance,
                $request->user(),
                $request->input('password', ''),
                $request
            );

            return response()->json([
                'message' => 'Cash advance request deleted successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
