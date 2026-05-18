<?php

namespace App\Modules\Reimbursements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Reimbursements\Models\Reimbursement;
use App\Modules\Reimbursements\Models\Receipt;

class ReimbursementController extends Controller
{
    /**
     * List all reimbursements.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Standard employees can only view their own claims; approvers and admins can view all.
        if ($user->role === 'employee') {
            $claims = Reimbursement::with('receipt')->where('user_id', $user->id)->get();
        } else {
            $claims = Reimbursement::with(['receipt', 'user'])->get();
        }

        return response()->json($claims);
    }

    /**
     * Submit a new reimbursement request.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'receipt_id' => 'nullable|exists:receipts,id',
        ]);

        $reimbursement = Reimbursement::create([
            'user_id' => $user->id,
            'receipt_id' => $validated['receipt_id'] ?? null,
            'description' => $validated['description'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'date' => $validated['date'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Reimbursement request submitted successfully.',
            'data' => $reimbursement,
        ], 210); // 201 Created
    }

    /**
     * View detailed claim.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $reimbursement = Reimbursement::with(['receipt', 'user'])->findOrFail($id);

        if ($user->role === 'employee' && $reimbursement->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($reimbursement);
    }

    /**
     * Approve claim.
     */
    public function approve(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role === 'employee') {
            return response()->json(['message' => 'Unauthorized. Only admins or approvers can perform this action.'], 403);
        }

        $reimbursement = Reimbursement::findOrFail($id);

        // Self-approval check
        if ($reimbursement->user_id === $user->id) {
            return response()->json(['message' => 'Conflict. Self-approval is strictly prohibited.'], 403);
        }

        $reimbursement->update(['status' => 'approved']);

        return response()->json([
            'message' => 'Reimbursement request approved.',
            'data' => $reimbursement,
        ]);
    }

    /**
     * Reject claim.
     */
    public function reject(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role === 'employee') {
            return response()->json(['message' => 'Unauthorized. Only admins or approvers can perform this action.'], 403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|min:5',
        ]);

        $reimbursement = Reimbursement::findOrFail($id);

        // Self-approval check
        if ($reimbursement->user_id === $user->id) {
            return response()->json(['message' => 'Conflict. Self-rejection/approval is prohibited.'], 403);
        }

        $reimbursement->update([
            'status' => 'rejected',
            'rejection_comment' => $validated['comment'],
        ]);

        return response()->json([
            'message' => 'Reimbursement request rejected.',
            'data' => $reimbursement,
        ]);
    }
}
