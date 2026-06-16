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

class CashAdvanceController extends Controller
{
    protected CashAdvanceService $service;

    public function __construct(CashAdvanceService $service)
    {
        $this->service = $service;
    }

    /**
     * List all cash advances.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', CashAdvance::class);

        $user = $request->user();

        if (!$user->can('serms.cash_advances.manage')) {
            $advances = CashAdvance::with(['approvalActions', 'penalties'])->where('user_id', $user->id)->get();
        } else {
            $advances = CashAdvance::with(['requester', 'approvalActions', 'penalties'])->get();
        }

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

        return response()->json([
            'message' => 'Cash advance request created successfully.',
            'data' => $advance->load('document'),
        ], 201);
    }

    /**
     * View detailed cash advance.
     */
    public function show(Request $request, $id)
    {
        $advance = CashAdvance::with(['requester', 'approvalActions', 'statusHistory', 'disbursement', 'penalties'])->findOrFail($id);

        Gate::authorize('view', $advance);

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

        $advance = $this->service->approveAdvance(
            $advance,
            $request->user(),
            $request->validated('comment')
        );

        return response()->json([
            'message' => 'Cash advance approved successfully.',
            'data' => $advance,
        ]);
    }

    /**
     * Reject a cash advance.
     */
    public function reject(RejectCashAdvanceRequest $request, $id)
    {
        $advance = CashAdvance::findOrFail($id);

        Gate::authorize('reject', $advance);

        if ($advance->status !== 'pending') {
            return response()->json(['message' => 'Conflict. Cash advance is not pending.'], 409);
        }

        $advance = $this->service->rejectAdvance(
            $advance,
            $request->user(),
            $request->validated('comment')
        );

        return response()->json([
            'message' => 'Cash advance rejected successfully.',
            'data' => $advance,
        ]);
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

        $advance = $this->service->disburseAdvance(
            $advance,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Cash advance disbursed successfully.',
            'data' => $advance,
        ]);
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

        return response()->json([
            'message' => 'Cash advance acknowledged successfully.',
            'data' => $advance,
        ]);
    }

    /**
     * Update a pending or rejected cash advance (employee self-edit).
     */
    public function update(UpdateCashAdvanceRequest $request, $id)
    {
        $advance = CashAdvance::findOrFail($id);

        // Only the owner can edit
        if ($advance->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden. You do not own this cash advance.'], 403);
        }

        if (!in_array($advance->status, ['pending', 'rejected'])) {
            return response()->json(['message' => 'Only pending or rejected cash advances can be edited.'], 409);
        }

        $advance = $this->service->updateAdvance(
            $advance,
            $request->user(),
            $request->validated(),
            $request->file('documents', [])
        );

        return response()->json([
            'message' => 'Cash advance updated successfully.',
            'data' => $advance->load('document'),
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
