<?php

namespace App\Modules\CashAdvances\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\CashAdvances\Models\CashAdvanceDocument;
use App\Modules\CashAdvances\Models\CashAdvanceApprovalAction;
use App\Modules\CashAdvances\Models\CashAdvanceDisbursement;
use App\Modules\CashAdvances\Models\CashAdvanceStatusHistory;

class CashAdvanceController extends Controller
{
    /**
     * List all cash advances.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'employee') {
            $advances = CashAdvance::with(['approvalActions'])->where('user_id', $user->id)->get();
        } else {
            $advances = CashAdvance::with(['requester', 'approvalActions'])->get();
        }

        return response()->json($advances);
    }

    /**
     * Request a new cash advance.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'purpose' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expected_disbursement_date' => 'required|date|after_or_equal:today',
            'expected_liquidation_date' => 'required|date|after:expected_disbursement_date',
            'documents' => 'required|array|max:5',
            'documents.*' => 'file|max:2048|mimes:pdf,doc,docx,xlsx,jpeg,png,jpg',
        ]);

        return DB::transaction(function () use ($validated, $user, $request) {
            $advance = CashAdvance::create([
                'user_id' => $user->id,
                'purpose' => $validated['purpose'],
                'amount' => $validated['amount'],
                'expected_disbursement_date' => $validated['expected_disbursement_date'],
                'expected_liquidation_date' => $validated['expected_liquidation_date'],
                'status' => 'pending',
            ]);

            CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => null,
                'to_status' => 'pending',
                'changed_by' => $user->id,
            ]);

            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('cash_advances/documents', 'supabase');

                    CashAdvanceDocument::create([
                        'cash_advance_id' => $advance->id,
                        'file_path' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'file_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Cash advance request created successfully.',
                'data' => $advance->load('document'),
            ], 201);
        });
    }

    /**
     * View detailed cash advance.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $advance = CashAdvance::with(['requester', 'approvalActions', 'statusHistory', 'disbursement'])->findOrFail($id);

        if ($user->role === 'employee' && $advance->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($advance);
    }

    /**
     * Get the document for a cash advance.
     */
    public function document(Request $request, $id)
    {
        $user = $request->user();
        $advance = CashAdvance::findOrFail($id);

        if ($user->role === 'employee' && $advance->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $document = $advance->document;
        return response()->json($document);
    }

    /**
     * Approve a cash advance.
     */
    public function approve(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only admins can approve cash advances.'], 403);
        }

        $advance = CashAdvance::findOrFail($id);

        if ($advance->user_id === $user->id) {
            return response()->json(['message' => 'Unauthorized. You cannot approve your own cash advance.'], 403);
        }

        if ($advance->status !== 'pending') {
            return response()->json(['message' => 'Conflict. Cash advance is not pending.'], 409);
        }

        $validated = $request->validate([
            'comment' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($advance, $user, $validated) {
            $advance->update(['status' => 'approved']);

            CashAdvanceApprovalAction::create([
                'cash_advance_id' => $advance->id,
                'approver_id' => $user->id,
                'action' => 'approved',
                'comment' => $validated['comment'] ?? null,
            ]);

            CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => 'pending',
                'to_status' => 'approved',
                'changed_by' => $user->id,
            ]);

            return response()->json([
                'message' => 'Cash advance approved successfully.',
                'data' => $advance,
            ]);
        });
    }

    /**
     * Reject a cash advance.
     */
    public function reject(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only admins can reject cash advances.'], 403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|min:5',
        ]);

        $advance = CashAdvance::findOrFail($id);

        if ($advance->user_id === $user->id) {
            return response()->json(['message' => 'Unauthorized. You cannot reject your own cash advance.'], 403);
        }

        if ($advance->status !== 'pending') {
            return response()->json(['message' => 'Conflict. Cash advance is not pending.'], 409);
        }

        return DB::transaction(function () use ($advance, $user, $validated) {
            $advance->update(['status' => 'rejected']);

            CashAdvanceApprovalAction::create([
                'cash_advance_id' => $advance->id,
                'approver_id' => $user->id,
                'action' => 'rejected',
                'comment' => $validated['comment'],
            ]);

            CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => 'pending',
                'to_status' => 'rejected',
                'changed_by' => $user->id,
            ]);

            return response()->json([
                'message' => 'Cash advance rejected successfully.',
                'data' => $advance,
            ]);
        });
    }

    /**
     * Disburse an approved cash advance.
     */
    public function disburse(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only admins can disburse cash advances.'], 403);
        }

        $validated = $request->validate([
            'channel' => 'required|string|max:100',
            'reference' => 'required|string|max:100',
        ]);

        $advance = CashAdvance::findOrFail($id);

        if ($advance->user_id === $user->id) {
            return response()->json(['message' => 'Unauthorized. You cannot disburse your own cash advance.'], 403);
        }

        if ($advance->status !== 'approved') {
            return response()->json(['message' => 'Conflict. Cash advance is not in an approved state.'], 409);
        }

        return DB::transaction(function () use ($advance, $user, $validated) {
            $advance->update(['status' => 'disbursed']);

            CashAdvanceDisbursement::create([
                'cash_advance_id' => $advance->id,
                'disbursed_by_id' => $user->id,
                'disbursement_date' => now()->toDateString(),
                'channel' => $validated['channel'],
                'reference_number' => $validated['reference'],
            ]);

            CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => 'approved',
                'to_status' => 'disbursed',
                'changed_by' => $user->id,
            ]);

            return response()->json([
                'message' => 'Cash advance disbursed successfully.',
                'data' => $advance,
            ]);
        });
    }

    /**
     * Acknowledge cash advance receipt.
     */
    public function acknowledge(Request $request, $id)
    {
        $user = $request->user();
        $advance = CashAdvance::findOrFail($id);

        if ($advance->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. You can only acknowledge your own cash advance.'], 403);
        }

        if (!in_array($advance->status, ['approved', 'disbursed'])) {
            return response()->json(['message' => 'Conflict. Cash advance is not in a valid state for acknowledgement.'], 409);
        }

        if ($advance->acknowledged_at !== null) {
            return response()->json(['message' => 'Conflict. Cash advance has already been acknowledged.'], 409);
        }

        $validated = $request->validate([
            'signature' => 'required|string',
        ]);

        return DB::transaction(function () use ($advance, $validated) {
            $advance->update([
                'signature' => $validated['signature'],
                'acknowledged_at' => now(),
            ]);

            return response()->json([
                'message' => 'Cash advance acknowledged successfully.',
                'data' => $advance,
            ]);
        });
    }
}
