<?php

namespace App\Modules\CashAdvances\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\CashAdvances\Models\CashAdvance;

class CashAdvanceController extends Controller
{
    /**
     * List all cash advances.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'employee') {
            $advances = CashAdvance::where('user_id', $user->id)->get();
        } else {
            $advances = CashAdvance::with('user')->get();
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
        ]);

        $advance = CashAdvance::create([
            'user_id' => $user->id,
            'purpose' => $validated['purpose'],
            'amount' => $validated['amount'],
            'expected_disbursement_date' => $validated['expected_disbursement_date'],
            'expected_liquidation_date' => $validated['expected_liquidation_date'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Cash advance request created successfully.',
            'data' => $advance,
        ], 210);
    }

    /**
     * View detailed cash advance.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $advance = CashAdvance::with('user')->findOrFail($id);

        if ($user->role === 'employee' && $advance->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($advance);
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

        if ($advance->status !== 'pending' && $advance->status !== 'approved') {
            return response()->json(['message' => 'Conflict. Cash advance is not in a disbursable state.'], 409);
        }

        $advance->update([
            'status' => 'disbursed',
            'disbursement_channel' => $validated['channel'],
            'disbursement_reference' => $validated['reference'],
            'disbursed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Cash advance disbursed successfully.',
            'data' => $advance,
        ]);
    }
}
