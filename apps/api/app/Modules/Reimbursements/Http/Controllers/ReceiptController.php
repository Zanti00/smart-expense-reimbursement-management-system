<?php

namespace App\Modules\Reimbursements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Reimbursements\Services\ReceiptService;
use App\Modules\Reimbursements\Http\Requests\StoreReceiptRequest;
use App\Modules\Reimbursements\Http\Requests\UpdateReceiptRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class ReceiptController extends Controller
{
    protected ReceiptService $service;

    public function __construct(ReceiptService $service)
    {
        $this->service = $service;
    }

    /**
     * List all receipts for the authenticated user.
     * Admins and approvers can see all receipts.
     */
    public function index(Request $request)
    {
        $canManage = $request->user()->can('serms.reimbursements.manage');
        $receipts = $this->service->listReceipts($request->user(), $canManage);

        return response()->json([
            'data' => $receipts,
        ]);
    }

    /**
     * Store a newly uploaded receipt in the database.
     */
    public function store(StoreReceiptRequest $request)
    {
        $receipt = $this->service->storeReceipt(
            $request->user(),
            $request->validated(),
            $request->file('file')
        );

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
        try {
            $canManage = $request->user()->can('serms.reimbursements.manage');
            
            $this->service->deleteReceipt(
                $request->user(),
                (int)$id,
                $canManage,
                $request->ip()
            );

            return response()->json([
                'message' => 'Receipt deleted successfully.'
            ], 200);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 403);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first('receipt') ?: $e->getMessage()
            ], 422);
        }
    }

    /**
     * Update receipt (admin notes, status).
     */
    public function update(UpdateReceiptRequest $request, $id)
    {
        try {
            $canManage = $request->user()->can('serms.reimbursements.manage');
            
            $receipt = $this->service->updateReceipt(
                $request->user(),
                (int)$id,
                $request->validated(),
                $canManage
            );

            return response()->json([
                'message' => 'Receipt updated successfully.',
                'data' => $receipt,
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }
}
