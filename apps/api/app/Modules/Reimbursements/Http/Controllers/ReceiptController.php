<?php

namespace App\Modules\Reimbursements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Reimbursements\Models\Receipt;
use App\Modules\Reimbursements\Models\Reimbursement;
use App\Modules\AuditLogs\Services\AuditLogService;
use App\Modules\Reimbursements\Jobs\ProcessReceiptOcr;
use Illuminate\Support\Facades\Storage;

class ReceiptController extends Controller
{
    /**
     * List all receipts for the authenticated user.
     * Admins and approvers can see all receipts.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Receipt::with('category', 'uploader', 'items');

        if (!$request->user()->can('serms.reimbursements.manage')) {
            $query->where('uploaded_by', $user->id);
        }

        $receipts = $query->orderByDesc('created_at')->get();

        return response()->json([
            'data' => $receipts,
        ]);
    }

    /**
     * Store a newly uploaded receipt in the database.
     */
    public function store(Request $request)
    {
        if ($request->has('items')) {
            $request->merge([
                'items' => is_string($request->items) ? json_decode($request->items, true) : $request->items,
            ]);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:jpeg,png,pdf|max:2048',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'vendor_name' => 'nullable|string|max:255',
            'transaction_date' => 'nullable|date',
            'total_amount' => 'nullable|numeric|min:0',
            'vat_amount' => 'nullable|numeric|min:0',
            'tin' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'vat_classification' => 'nullable|in:vat,non-vat',
            'items' => 'nullable|array',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.price' => 'required_with:items|numeric|min:0',
        ]);

        $path = null;
        $fileHash = null;
        $fileType = null;
        $fileSize = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            
            // Store in Supabase bucket as per requirements
            $path = $file->store('receipts', 'supabase');
            $fileHash = hash_file('sha256', $file->getRealPath());
            
            $fileType = $file->extension();
            if ($fileType === 'jpg') {
                $fileType = 'jpeg';
            }
            $fileSize = $file->getSize();
        }

        $receipt = Receipt::create([
            'uploaded_by' => $request->user()->id,
            'file_path' => $path,
            'file_hash' => $fileHash,
            'file_type' => $fileType,
            'file_size_bytes' => $fileSize,
            'expense_category_id' => $validated['expense_category_id'],
            'vendor_name' => $validated['vendor_name'] ?? null,
            'transaction_date' => $validated['transaction_date'] ?? null,
            'total_amount' => $validated['total_amount'] ?? null,
            'vat_amount' => $validated['vat_amount'] ?? null,
            'tin' => $validated['tin'] ?? null,
            'invoice_number' => $validated['invoice_number'] ?? null,
            'vat_classification' => $validated['vat_classification'] ?? null,
            'ocr_flagged' => false,
            'is_archived' => false,
            'status' => 'processing',
        ]);

        if (!empty($validated['items'])) {
            $receipt->items()->createMany($validated['items']);
        }

        ProcessReceiptOcr::dispatch($receipt);

        // Load relations for the response
        $receipt->load('category', 'items', 'uploader');

        return response()->json([
            'message' => 'Receipt uploaded and stored successfully.',
            'data' => $receipt,
        ], 201);
    }

    /**
     * Delete a receipt.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $receipt = Receipt::findOrFail($id);

        // Check RBAC: Only uploader or admin can delete
        if ($receipt->uploaded_by !== $user->id && !$request->user()->can('serms.reimbursements.manage')) {
            return response()->json([
                'message' => 'Unauthorized. You can only delete your own receipts.'
            ], 403);
        }

        // Check constraints: Block deletion if linked to a Reimbursement
        if (Reimbursement::where('receipt_id', $receipt->id)->exists()) {
            return response()->json([
                'message' => 'Cannot delete a receipt that is attached to a reimbursement.'
            ], 422);
        }

        // Soft delete the receipt
        $receipt->delete();

        // Audit Log
        AuditLogService::log(
            actorId: $user->id,
            actorRole: $user->role,
            actionType: 'RECEIPT_DELETED',
            entityType: 'receipt',
            entityId: $receipt->id,
            beforeState: $receipt->toArray(),
            afterState: ['deleted_at' => now()->toDateTimeString()],
            ipAddress: $request->ip()
        );

        return response()->json([
            'message' => 'Receipt deleted successfully.'
        ], 200);
    }

    /**
     * Update receipt (admin notes, status).
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$request->user()->can('serms.reimbursements.manage')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string',
            'status' => 'nullable|string|in:pending,approved,rejected',
        ]);

        $receipt = Receipt::findOrFail($id);
        $receipt->update($validated);

        return response()->json([
            'message' => 'Receipt updated successfully.',
            'data' => $receipt,
        ]);
    }
}
