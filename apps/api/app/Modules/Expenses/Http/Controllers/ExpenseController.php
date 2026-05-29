<?php

namespace App\Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Reimbursements\Models\Receipt;

class ExpenseController extends Controller
{
    /**
     * List all receipts for the authenticated user (role-scoped).
     * Expenses are receipts not yet linked to a reimbursement.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Receipt::query()->whereNull('deleted_at');

        // Standard employees can only view their own receipts
        if ($user->role === 'employee') {
            $query->where('uploaded_by', $user->id);
        } else {
            $query->with('uploader');
        }

        // Apply active filter query parameters
        if ($request->filled('uploader_id')) {
            $query->where('uploaded_by', $request->query('uploader_id'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('min_amount')) {
            $query->where('total_amount', '>=', $request->query('min_amount'));
        }

        if ($request->filled('max_amount')) {
            $query->where('total_amount', '<=', $request->query('max_amount'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->query('end_date'));
        }

        $receipts = $query->orderBy('created_at', 'desc')->get();

        return response()->json($receipts);
    }

    /**
     * Store a new receipt record from the expense management form.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'file_path' => 'required|string|max:500',
            'file_hash' => 'required|string|size:64',
            'file_type' => 'required|in:jpeg,png,pdf',
            'file_size_bytes' => 'required|integer|min:1',
            'vendor_name' => 'nullable|string|max:255',
            'transaction_date' => 'nullable|date',
            'total_amount' => 'nullable|numeric|min:0',
            'vat_amount' => 'nullable|numeric|min:0',
            'tin' => 'nullable|string|max:20',
            'invoice_number' => 'nullable|string|max:100',
            'vat_classification' => 'nullable|in:vat,non-vat',
            'ocr_confidence_score' => 'nullable|numeric|min:0|max:100',
            'category' => 'nullable|string|max:100',
        ]);

        // Duplicate hash check
        $existingHash = Receipt::where('file_hash', $validated['file_hash'])
            ->whereNull('deleted_at')
            ->exists();

        if ($existingHash) {
            return response()->json([
                'message' => 'Duplicate detected. A receipt with this file hash already exists.',
            ], 409);
        }

        $receipt = Receipt::create([
            'uploaded_by' => $user->id,
            'file_path' => $validated['file_path'],
            'file_hash' => $validated['file_hash'],
            'file_type' => $validated['file_type'],
            'file_size_bytes' => $validated['file_size_bytes'],
            'vendor_name' => $validated['vendor_name'] ?? null,
            'transaction_date' => $validated['transaction_date'] ?? null,
            'total_amount' => $validated['total_amount'] ?? null,
            'vat_amount' => $validated['vat_amount'] ?? null,
            'tin' => $validated['tin'] ?? null,
            'invoice_number' => $validated['invoice_number'] ?? null,
            'vat_classification' => $validated['vat_classification'] ?? null,
            'ocr_confidence_score' => $validated['ocr_confidence_score'] ?? null,
            'ocr_flagged' => ($validated['ocr_confidence_score'] ?? 100) < 80,
            'category' => $validated['category'] ?? null,
        ]);

        return response()->json([
            'message' => 'Receipt stored successfully.',
            'data' => $receipt,
        ], 201);
    }

    /**
     * View a single receipt record.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $receipt = Receipt::with('uploader')->findOrFail($id);

        if ($user->role === 'employee' && $receipt->uploaded_by !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($receipt);
    }

    /**
     * Update receipt metadata (OCR-extracted fields editable by owner).
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $receipt = Receipt::findOrFail($id);

        if ($receipt->uploaded_by !== $user->id && $user->role === 'employee') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'vendor_name' => 'nullable|string|max:255',
            'transaction_date' => 'nullable|date',
            'total_amount' => 'nullable|numeric|min:0',
            'vat_amount' => 'nullable|numeric|min:0',
            'tin' => 'nullable|string|max:20',
            'invoice_number' => 'nullable|string|max:100',
            'vat_classification' => 'nullable|in:vat,non-vat',
            'category' => 'nullable|string|max:100',
        ]);

        $receipt->update($validated);

        return response()->json([
            'message' => 'Receipt updated successfully.',
            'data' => $receipt->fresh(),
        ]);
    }

    /**
     * Soft-delete a receipt record.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $receipt = Receipt::findOrFail($id);

        if ($user->role === 'employee' && $receipt->uploaded_by !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $receipt->delete();

        return response()->json([
            'message' => 'Receipt deleted successfully.',
        ]);
    }
}
