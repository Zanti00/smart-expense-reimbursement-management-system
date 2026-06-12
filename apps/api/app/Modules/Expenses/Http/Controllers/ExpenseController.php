<?php

namespace App\Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Expenses\Services\ExpenseService;
use App\Modules\Expenses\Http\Requests\StoreReceiptRequest;
use App\Modules\Expenses\Http\Requests\UpdateReceiptRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class ExpenseController extends Controller
{
    protected ExpenseService $service;

    public function __construct(ExpenseService $service)
    {
        $this->service = $service;
    }

    /**
     * List all receipts for the authenticated user (role-scoped).
     * Expenses are receipts not yet linked to a reimbursement.
     */
    public function index(Request $request)
    {
        $receipts = $this->service->listReceipts($request->user(), $request->query());

        return response()->json($receipts);
    }

    /**
     * Store a new receipt record from the expense management form.
     */
    public function store(StoreReceiptRequest $request)
    {
        try {
            $receipt = $this->service->storeReceipt($request->user(), $request->validated());

            return response()->json([
                'message' => 'Receipt stored successfully.',
                'data' => $receipt,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first('file_hash') ?: $e->getMessage(),
            ], 409);
        }
    }

    /**
     * View a single receipt record.
     */
    public function show(Request $request, $id)
    {
        try {
            $canManage = $request->user()->can('serms.reimbursements.manage');
            $receipt = $this->service->showReceipt($request->user(), (int)$id, $canManage);

            return response()->json($receipt);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Update receipt metadata (OCR-extracted fields editable by owner).
     */
    public function update(UpdateReceiptRequest $request, $id)
    {
        try {
            $canManage = $request->user()->can('serms.reimbursements.manage');
            $receipt = $this->service->updateReceipt($request->user(), (int)$id, $request->validated(), $canManage);

            return response()->json([
                'message' => 'Receipt updated successfully.',
                'data' => $receipt,
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Soft-delete a receipt record.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $canManage = $request->user()->can('serms.reimbursements.manage');
            $this->service->deleteReceipt($request->user(), (int)$id, $canManage);

            return response()->json([
                'message' => 'Receipt deleted successfully.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }
}
