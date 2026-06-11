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
        if (!$request->user()->can('serms.reimbursements.manage')) {
            $claims = Reimbursement::with(['receipts', 'user'])->where('user_id', $user->id)->get();
        } else {
            $claims = Reimbursement::with(['receipts', 'user'])->get();
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
            'cutoff_period' => 'required|string|max:255',
            'report_file' => 'required|file|mimes:jpeg,png,pdf|max:2048',
            'receipt_ids' => 'required|array|min:1',
            'receipt_ids.*' => 'exists:receipts,id',
            'receipts' => 'nullable|array',
            'receipts.*.id' => 'required_with:receipts|exists:receipts,id',
            'receipts.*.vendor_name' => 'nullable|string|max:255',
            'receipts.*.transaction_date' => 'nullable|date',
            'receipts.*.total_amount' => 'nullable|numeric|min:0',
            'receipts.*.tin' => 'nullable|string|max:255',
            'receipts.*.invoice_number' => 'nullable|string|max:255',
        ]);

        if (!empty($validated['receipts'])) {
            foreach ($validated['receipts'] as $receiptData) {
                $receipt = Receipt::find($receiptData['id']);
                if ($receipt && $receipt->uploaded_by === $user->id) {
                    $receipt->update([
                        'vendor_name' => $receiptData['vendor_name'] ?? $receipt->vendor_name,
                        'transaction_date' => $receiptData['transaction_date'] ?? $receipt->transaction_date,
                        'total_amount' => $receiptData['total_amount'] ?? $receipt->total_amount,
                        'tin' => $receiptData['tin'] ?? $receipt->tin,
                        'invoice_number' => $receiptData['invoice_number'] ?? $receipt->invoice_number,
                    ]);
                }
            }
        }

        $reportPath = null;
        if ($request->hasFile('report_file')) {
            $reportPath = $request->file('report_file')->store('reports', 'supabase');
        }

        $reimbursement = Reimbursement::create([
            'user_id' => $user->id,
            'description' => $validated['description'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'date' => $validated['date'],
            'cutoff_period' => $validated['cutoff_period'],
            'report_file_path' => $reportPath,
            'status' => 'submitted',
            'submitted_by_name' => $user->name, // Assuming name exists
        ]);

        $reimbursement->receipts()->attach($validated['receipt_ids']);

        return response()->json([
            'message' => 'Reimbursement request submitted successfully.',
            'data' => $reimbursement->load('receipts'),
        ], 201);
    }

    /**
     * View detailed claim.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $reimbursement = Reimbursement::with(['receipts', 'user'])->findOrFail($id);

        if (!$request->user()->can('serms.reimbursements.manage') && $reimbursement->user_id !== $user->id) {
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

        if (!$request->user()->can('serms.reimbursements.manage')) {
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

        if (!$request->user()->can('serms.reimbursements.manage')) {
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

    /**
     * Update reimbursement details (admin notes, status).
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$request->user()->can('serms.reimbursements.manage')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string',
            'status' => 'nullable|string|in:pending,submitted,approved,rejected,granted',
        ]);

        $reimbursement = Reimbursement::findOrFail($id);
        
        $reimbursement->update($validated);

        return response()->json([
            'message' => 'Reimbursement updated successfully.',
            'data' => $reimbursement,
        ]);
    }
}
