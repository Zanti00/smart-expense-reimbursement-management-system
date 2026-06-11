<?php

namespace App\Modules\Liquidations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Liquidations\Models\Liquidation;
use App\Modules\CashAdvances\Models\CashAdvance;

class LiquidationController extends Controller
{
    /**
     * List all liquidations.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$request->user()->can('serms.liquidations.manage')) {
            $liquidations = Liquidation::where('user_id', $user->id)->get();
        } else {
            $liquidations = Liquidation::with(['user', 'cashAdvance'])->get();
        }

        return response()->json($liquidations);
    }

    /**
     * Submit a cash advance liquidation.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'cash_advance_id' => 'required|exists:cash_advances,id',
            'reimbursement_ids' => 'required|array',
            'reimbursement_ids.*' => 'integer',
            'total_expense_amount' => 'required|numeric|min:0.00',
            'shortfall_explanation' => 'nullable|string',
        ]);

        $advance = CashAdvance::findOrFail($validated['cash_advance_id']);

        if ($advance->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You do not own this cash advance.'], 403);
        }

        if ($advance->status !== 'disbursed' && $advance->status !== 'overdue') {
            return response()->json(['message' => 'Conflict. Cash advance is not in a reconcilable state.'], 409);
        }

        // Calculate variance (Cash Advance Amount - Total Expense)
        // If variance > 0: Shortfall exists (User spent less than advanced, must return surplus)
        // If variance < 0: Abono exists (User spent more than advanced, company must reimburse user)
        $variance = $advance->amount - $validated['total_expense_amount'];

        // Enforce: shortfalls require an explanation
        if ($variance > 0 && empty($validated['shortfall_explanation'])) {
            return response()->json([
                'message' => 'Validation error.',
                'errors' => [
                    'shortfall_explanation' => ['Shortfall explanation is required when total expense is less than the advanced amount.']
                ]
            ], 422);
        }

        $liquidation = Liquidation::create([
            'cash_advance_id' => $advance->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'reimbursement_ids' => $validated['reimbursement_ids'],
            'total_expense_amount' => $validated['total_expense_amount'],
            'variance_amount' => $variance,
            'shortfall_explanation' => $validated['shortfall_explanation'] ?? null,
        ]);

        // Temporarily change advance status to pending liquidation audit
        $advance->update(['status' => 'approved']); // Approvals lock the unliquidated state during review

        return response()->json([
            'message' => 'Liquidation settlement submitted for audit successfully.',
            'data' => $liquidation,
        ], 210);
    }

    /**
     * Show detailed liquidation details.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $liquidation = Liquidation::with(['user', 'cashAdvance'])->findOrFail($id);

        if (!$request->user()->can('serms.liquidations.manage') && $liquidation->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($liquidation);
    }

    /**
     * Audit and close a liquidation settlement.
     */
    public function audit(Request $request, $id)
    {
        $user = $request->user();

        if (!$request->user()->can('serms.liquidations.manage')) {
            return response()->json(['message' => 'Unauthorized. Only admins can audit liquidations.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $liquidation = Liquidation::findOrFail($id);
        $advance = CashAdvance::findOrFail($liquidation->cash_advance_id);

        if ($validated['status'] === 'approved') {
            $liquidation->update(['status' => 'liquidated']);
            $advance->update(['status' => 'liquidated']); // Debt cleared!
        } else {
            $liquidation->update(['status' => 'rejected']);
            $advance->update(['status' => 'disbursed']); // Return to unliquidated active debt
        }

        return response()->json([
            'message' => 'Liquidation settlement audit complete.',
            'data' => $liquidation,
        ]);
    }
}
