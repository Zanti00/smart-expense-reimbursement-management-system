<?php

namespace App\Modules\Reimbursements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Reimbursements\Services\ReimbursementService;
use App\Modules\Reimbursements\Http\Requests\StoreReimbursementRequest;
use App\Modules\Reimbursements\Http\Requests\ApproveReimbursementRequest;
use App\Modules\Reimbursements\Http\Requests\RejectReimbursementRequest;
use App\Modules\Reimbursements\Http\Requests\UpdateReimbursementRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class ReimbursementController extends Controller
{
    protected ReimbursementService $service;

    public function __construct(ReimbursementService $service)
    {
        $this->service = $service;
    }

    /**
     * List all reimbursements.
     */
    public function index(Request $request)
    {
        $canManage = $request->user()->can('serms.reimbursements.manage');
        $claims = $this->service->listReimbursements($request->user(), $canManage);

        return response()->json($claims);
    }

    /**
     * Submit a new reimbursement request.
     */
    public function store(StoreReimbursementRequest $request)
    {
        $reimbursement = $this->service->storeReimbursement(
            $request->user(),
            $request->validated(),
            $request->file('report_file')
        );

        return response()->json([
            'message' => 'Reimbursement request submitted successfully.',
            'data' => $reimbursement,
        ], 201);
    }

    /**
     * View detailed claim.
     */
    public function show(Request $request, $id)
    {
        try {
            $canManage = $request->user()->can('serms.reimbursements.manage');
            $reimbursement = $this->service->showReimbursement($request->user(), (int)$id, $canManage);

            return response()->json($reimbursement);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Approve claim.
     */
    public function approve(ApproveReimbursementRequest $request, $id)
    {
        try {
            $reimbursement = $this->service->approveReimbursement(
                $request->user(),
                (int)$id,
                $request->validated('password'),
                $request->ip(),
                $request
            );

            return response()->json([
                'message' => 'Reimbursement request approved.',
                'data' => $reimbursement,
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Reject claim.
     */
    public function reject(RejectReimbursementRequest $request, $id)
    {
        try {
            $reimbursement = $this->service->rejectReimbursement(
                $request->user(),
                (int)$id,
                $request->validated('comment'),
                $request->validated('password'),
                $request->ip(),
                $request
            );

            return response()->json([
                'message' => 'Reimbursement request rejected.',
                'data' => $reimbursement,
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Update reimbursement details (admin notes, status).
     */
    public function update(UpdateReimbursementRequest $request, $id)
    {
        try {
            $canManage = $request->user()->can('serms.reimbursements.manage');
            
            $reimbursement = $this->service->updateReimbursement(
                $request->user(),
                (int)$id,
                $request->validated(),
                $canManage
            );

            return response()->json([
                'message' => 'Reimbursement updated successfully.',
                'data' => $reimbursement,
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }
}
