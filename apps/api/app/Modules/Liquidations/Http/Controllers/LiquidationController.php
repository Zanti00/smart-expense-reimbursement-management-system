<?php

namespace App\Modules\Liquidations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Liquidations\Models\Liquidation;
use App\Modules\CashAdvances\Models\CashAdvance;
use App\Modules\Reimbursements\Models\Receipt;
use App\Modules\Ai\Contracts\OcrEngineInterface;
use App\Modules\Shared\Services\PasswordVerificationService;
use App\Modules\AuditLogs\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class LiquidationController extends Controller
{
    protected OcrEngineInterface $ocrEngine;

    public function __construct(OcrEngineInterface $ocrEngine)
    {
        $this->ocrEngine = $ocrEngine;
    }

    /**
     * CashAdvance relations needed for Unified Roadmap when accessed via liquidation
     */
    private const CASH_ADVANCE_ROADMAP_WITH = [
        'requester',
        'approvalActions.approver',
        'statusHistory.changedBy',
        'statusHistory.user',
        'disbursement.disbursedBy',
        'penalties',
        'liquidations',
        'document',
    ];

    private function enrichCashAdvanceForRoadmap(?CashAdvance $advance): ?CashAdvance
    {
        if (!$advance) {
            return null;
        }
        // Ensure penalties_total - use withSum if available else collection sum
        if ($advance->relationLoaded('penalties')) {
            $penaltiesTotal = $advance->penalties->sum(fn ($p) => (float) ($p->penalty_amount ?? 0));
            $advance->setAttribute('penalties_total', (float) $penaltiesTotal);
            $advance->setAttribute('penalties_sum', (float) $penaltiesTotal);
        } elseif (!isset($advance->penalties_total)) {
            $advance->setAttribute('penalties_total', (float) \App\Modules\Liquidations\Models\PenaltyRecord::where('cash_advance_id', $advance->id)->sum('penalty_amount'));
            $advance->setAttribute('penalties_sum', (float) $advance->penalties_total);
        }

        // days_overdue
        $due = $advance->expected_liquidation_date;
        $daysOverdue = 0;
        if ($due) {
            $today = Carbon::now()->startOfDay();
            $dueDate = Carbon::parse($due)->startOfDay();
            $isTerminal = in_array($advance->status, ['liquidated', 'settled', 'rejected']);
            $isAuditing = in_array($advance->status, ['pending', 'under-review', 'approved']);
            if ($today->gt($dueDate) && !$isTerminal && !$isAuditing) {
                $daysOverdue = $today->diffInDays($dueDate);
            } elseif ($advance->relationLoaded('penalties') && $advance->penalties->isNotEmpty()) {
                $maxDays = $advance->penalties->max('days_overdue');
                if ($maxDays) {
                    $daysOverdue = max($daysOverdue, (int) $maxDays);
                }
            }
        } elseif ($advance->relationLoaded('penalties') && $advance->penalties->isNotEmpty()) {
            $daysOverdue = (int) ($advance->penalties->max('days_overdue') ?? $advance->penalties->count());
        }
        $advance->setAttribute('days_overdue', (int) $daysOverdue);

        // linked_liquidation aliases
        if ($advance->relationLoaded('liquidations')) {
            $latest = $advance->liquidations->sortByDesc('created_at')->sortByDesc('id')->first();
            $advance->setAttribute('linked_liquidation', $latest);
            $advance->setAttribute('latest_liquidation', $latest);
            $advance->setAttribute('liquidation', $latest);
        }

        // status_history normalized for roadmap
        if ($advance->relationLoaded('statusHistory')) {
            $history = $advance->statusHistory->sortBy('changed_at')->sortBy('id')->map(function ($h) {
                $actor = $h->relationLoaded('changedBy') && $h->changedBy ? $h->changedBy : ($h->relationLoaded('user') ? $h->user : null);
                return [
                    'id' => $h->id,
                    'cash_advance_id' => $h->cash_advance_id,
                    'from_status' => $h->from_status,
                    'to_status' => $h->to_status,
                    'status' => $h->to_status,
                    'changed_by' => $actor ? ['id' => $actor->id, 'name' => $actor->name, 'email' => $actor->email] : null,
                    'changed_by_id' => $h->changed_by,
                    'actor' => $actor?->name,
                    'user' => $actor ? ['id' => $actor->id, 'name' => $actor->name, 'email' => $actor->email] : null,
                    'changed_at' => $h->changed_at ?? $h->created_at,
                    'created_at' => $h->created_at,
                ];
            })->values();
            $advance->setAttribute('status_history', $history);
        }

        // Ensure outstanding_balance / expected_liquidation_date present
        $advance->setAttribute('balance', $advance->outstanding_balance !== null ? (float) $advance->outstanding_balance : (float) ($advance->amount ?? 0));
        $advance->setAttribute('outstanding_balance', $advance->outstanding_balance);
        $advance->setAttribute('expected_liquidation_date', $advance->expected_liquidation_date);

        return $advance;
    }

    private function hydrateLiquidation(Liquidation $liq): Liquidation
    {
        // Hydrate receipts
        $receiptIds = $liq->reimbursement_ids ?? [];
        if (is_array($receiptIds) && !empty($receiptIds)) {
            $liq->setAttribute('receipts', Receipt::whereIn('id', $receiptIds)->with('items')->get());
        } else {
            $liq->setAttribute('receipts', collect());
        }

        // Enrich nested cashAdvance for Unified Roadmap (no extra fetch)
        if ($liq->relationLoaded('cashAdvance') && $liq->cashAdvance) {
            $this->enrichCashAdvanceForRoadmap($liq->cashAdvance);
            // Also compute overpayment / aging on liquidation itself for frontend convenience
            $ca = $liq->cashAdvance;
            $overpayment = 0;
            $expense = (float) ($liq->total_expense_amount ?? 0);
            $snapshot = (float) ($liq->outstanding_balance ?? $ca->outstanding_balance ?? $ca->amount ?? 0);
            if ($expense > $snapshot) {
                $overpayment = $expense - $snapshot;
            }
            $liq->setAttribute('overpayment_amount', (float) $overpayment);
            $liq->setAttribute('overpaymentAmount', (float) $overpayment);
            $liq->setAttribute('penalties_total', (float) ($ca->penalties_total ?? 0));
            $liq->setAttribute('days_overdue', (int) ($ca->days_overdue ?? 0));
            // Ensure liquidation calc variance fields present
            $liq->setAttribute('variance', $snapshot - $expense);
        }

        return $liq;
    }

    /**
     * List all liquidations.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $with = array_merge(['user', 'cashAdvance'], array_map(fn($r) => "cashAdvance.$r", self::CASH_ADVANCE_ROADMAP_WITH));

        if (!$request->user()->can('serms.liquidations.manage')) {
            $liquidations = Liquidation::where('user_id', $user->id)
                ->with($with)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();
        } else {
            $liquidations = Liquidation::with($with)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();
        }

        foreach ($liquidations as $liq) {
            $this->hydrateLiquidation($liq);
        }

        return response()->json($liquidations);
    }

    /**
     * Scan a receipt image/pdf and extract OCR fields synchronously.
     */
    public function scan(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|mimes:jpeg,png,pdf|max:2048',
        ]);

        $files = $request->file('files');
        
        $tempPaths = [];
        $filePaths = [];
        $fileHashes = [];
        $fileTypes = [];
        $fileSizes = [];

        foreach ($files as $file) {
            // Save file to a temp path for Tesseract engine execution
            $tempPath = tempnam(sys_get_temp_dir(), 'ocr_') . '.' . $file->extension();
            file_put_contents($tempPath, file_get_contents($file->getRealPath()));
            $tempPaths[] = $tempPath;

            // Upload/store in Supabase bucket
            $path = $file->store('receipts', 'supabase');
            $filePaths[] = $path;
            
            $fileHashes[] = hash_file('sha256', $file->getRealPath());
            
            $fileType = $file->extension();
            if ($fileType === 'jpg') {
                $fileType = 'jpeg';
            }
            $fileTypes[] = $fileType;
            
            $fileSizes[] = $file->getSize();
        }

        $extractedData = $this->ocrEngine->extractReceiptData($tempPaths);
        
        foreach ($tempPaths as $tempPath) {
            @unlink($tempPath);
        }

        // Store the receipt in database with temporary "processing" status
        $receipt = Receipt::create([
            'uploaded_by' => $request->user()->id,
            'file_path' => $filePaths,
            'file_hash' => $fileHashes,
            'file_type' => $fileTypes,
            'file_size_bytes' => $fileSizes,
            'vendor_name' => $extractedData['vendor_name'] ?? null,
            'transaction_date' => $extractedData['transaction_date'] ?? null,
            'total_amount' => $extractedData['total_amount'] ?? null,
            'vat_amount' => $extractedData['vat_amount'] ?? null,
            'tin' => $extractedData['tin'] ?? null,
            'invoice_number' => $extractedData['invoice_number'] ?? null,
            'ocr_confidence_score' => $extractedData['ocr_confidence_score'] ?? 85.00,
            'ocr_flagged' => ($extractedData['ocr_confidence_score'] ?? 85.00) < 80,
            'status' => 'processing',
        ]);

        return response()->json([
            'data' => [
                'id' => $receipt->id,
                'vendor_name' => $receipt->vendor_name,
                'transaction_date' => $receipt->transaction_date ? $receipt->transaction_date->format('Y-m-d') : null,
                'total_amount' => $receipt->total_amount,
                'vat_amount' => $receipt->vat_amount,
                'tin' => $receipt->tin,
                'invoice_number' => $receipt->invoice_number,
                'ocr_confidence_score' => $receipt->ocr_confidence_score,
                'file_path' => $filePaths,
                'file_hash' => $fileHashes,
                'file_type' => $fileTypes,
                'file_size_bytes' => $fileSizes,
            ]
        ]);
    }

    /**
     * Submit a cash advance liquidation.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        return DB::transaction(function () use ($user, $request) {
            $validated = $request->validate([
                'cash_advance_id' => 'required|exists:cash_advances,id',
                'receipts' => 'required', // JSON string for multipart or array for json request
                'report_attachment' => 'nullable|file|mimes:jpeg,png,pdf,doc,docx|max:5120',
                'total_expense_amount' => 'required|numeric|min:0.00',
                'shortfall_explanation' => 'nullable|string',
            ]);

            // Decode receipts JSON string if it is not already an array
            $receiptsData = is_string($validated['receipts'])
                ? json_decode($validated['receipts'], true)
                : $validated['receipts'];

            if (!is_array($receiptsData)) {
                return response()->json(['message' => 'Invalid receipts data format.'], 422);
            }

            $advance = CashAdvance::findOrFail($validated['cash_advance_id']);

            if ($advance->user_id !== $user->id) {
                return response()->json(['message' => 'Forbidden. You do not own this cash advance.'], 403);
            }

            if (!in_array($advance->status, ['disbursed', 'signed', 'overdue', 'incomplete'])) {
                return response()->json(['message' => 'Conflict. Cash advance is not in a reconcilable state.'], 409);
            }

            // Update each receipt in database with user-edited fields
            $receiptIds = [];
            foreach ($receiptsData as $receiptData) {
                if (!isset($receiptData['id'])) continue;
                $receipt = Receipt::findOrFail($receiptData['id']);
                $receipt->update([
                    'vendor_name' => $receiptData['vendor_name'] ?? $receiptData['vendor'] ?? $receipt->vendor_name,
                    'transaction_date' => $receiptData['transaction_date'] ?? $receiptData['date'] ?? $receipt->transaction_date,
                    'total_amount' => $receiptData['total_amount'] ?? $receiptData['amount'] ?? $receipt->total_amount,
                    'vat_amount' => $receiptData['vat_amount'] ?? $receiptData['vat'] ?? $receiptData['tax'] ?? $receipt->vat_amount,
                    'tin' => $receiptData['tin'] ?? $receipt->tin,
                    'invoice_number' => $receiptData['invoice_number'] ?? $receiptData['invoiceNumber'] ?? $receipt->invoice_number,
                    'expense_category_id' => $receiptData['expense_category_id'] ?? $receiptData['categoryId'] ?? $receipt->expense_category_id ?? null,
                    'location' => $receiptData['location'] ?? $receipt->location ?? null,
                    'currency' => $receiptData['currency'] ?? $receipt->currency ?? 'PHP',
                    'vat_classification' => $receiptData['vat_classification'] ?? $receiptData['vatClassification'] ?? $receipt->vat_classification ?? 'vat',
                    'status' => 'pending', // Awaiting admin audit
                ]);

                if (isset($receiptData['items']) && is_array($receiptData['items'])) {
                    $receipt->items()->delete();
                    foreach ($receiptData['items'] as $item) {
                        if (!empty($item['name']) || isset($item['price'])) {
                            $receipt->items()->create([
                                'name' => $item['name'] ?? 'Item',
                                'qty' => $item['qty'] ?? 1,
                                'price' => $item['price'] ?? 0,
                            ]);
                        }
                    }
                }

                $receiptIds[] = $receipt->id;
            }

            // Snapshot the cash advance's current outstanding balance
            $currentOutstandingBalance = $advance->outstanding_balance ?? $advance->amount;

            // Calculate variance based on the current balance, not original amount
            $variance = $currentOutstandingBalance - $validated['total_expense_amount'];

            // Enforce: shortfalls require an explanation
            if ($variance > 0 && empty($validated['shortfall_explanation'])) {
                return response()->json([
                    'message' => 'Validation error.',
                    'errors' => [
                        'shortfall_explanation' => ['Shortfall explanation is required when total expense is less than the advanced amount.']
                    ]
                ], 422);
            }

            // Handle Report Attachment Upload
            $reportFilePath = null;
            if ($request->hasFile('report_attachment')) {
                $file = $request->file('report_attachment');
                $reportFilePath = $file->store('report_letters', 'supabase');
            }

            $liquidation = Liquidation::create([
                'cash_advance_id' => $advance->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'reimbursement_ids' => $receiptIds,
                'total_expense_amount' => $validated['total_expense_amount'],
                'outstanding_balance' => $currentOutstandingBalance,
                'shortfall_explanation' => $validated['shortfall_explanation'] ?? null,
                'report_file_path' => $reportFilePath,
            ]);

            // Change advance status to under-review while admin audits
            $advance->update(['status' => 'under-review']);

            // Create status history log for cash advance
            \App\Modules\CashAdvances\Models\CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => $advance->status,
                'to_status' => 'under-review',
                'changed_by' => $user->id,
            ]);

            // Audit Log
            AuditLogService::log(
                actorId: $user->id,
                actorRole: $user->role,
                actionType: 'LIQUIDATION_SUBMITTED',
                entityType: 'liquidation',
                entityId: $liquidation->id,
                beforeState: null,
                afterState: $liquidation->toArray(),
                ipAddress: $request->ip()
            );

            return response()->json([
                'message' => 'Liquidation settlement submitted for audit successfully.',
                'data' => $liquidation,
            ], 210);
        });
    }

    /**
     * Show detailed liquidation details.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $with = array_merge(['user', 'cashAdvance'], array_map(fn($r) => "cashAdvance.$r", self::CASH_ADVANCE_ROADMAP_WITH));
        $liquidation = Liquidation::with($with)->findOrFail($id);

        if (!$request->user()->can('serms.liquidations.manage') && $liquidation->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->hydrateLiquidation($liquidation);

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
            'status' => 'required|in:approved,revise,rejected',
            'password' => 'required|string',
            'admin_note' => 'nullable|string|min:10|required_if:status,revise|required_if:status,rejected',
        ]);

        return DB::transaction(function () use ($user, $id, $validated, $request) {
            // Verify password against external auth service
            if (!PasswordVerificationService::verify($request, $validated['password'])) {
                throw ValidationException::withMessages([
                    'password' => ['Invalid password. Please try again.']
                ]);
            }

            $liquidation = Liquidation::findOrFail($id);
            $advance = CashAdvance::findOrFail($liquidation->cash_advance_id);
            $receiptIds = is_array($liquidation->reimbursement_ids) ? $liquidation->reimbursement_ids : [];

            // Self-approval/self-rejection check
            if ($liquidation->user_id === $user->id) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Conflict. Self-audit is prohibited.');
            }

            $beforeState = $liquidation->toArray();

            if ($validated['status'] === 'approved') {
                $currentBalance  = (float) ($advance->outstanding_balance ?? $advance->amount);
                $approvedExpense = (float) $liquidation->total_expense_amount;
                $newBalance      = max($currentBalance - $approvedExpense, 0);
                
                $variance = (float) $liquidation->outstanding_balance - $approvedExpense;
                $newAdvanceStatus = ($variance > 0) ? 'incomplete' : 'liquidated';

                $liquidation->update([
                    'status'              => 'liquidated',
                    'admin_note'          => $validated['admin_note'] ?? null,
                    'outstanding_balance' => $newBalance,
                ]);

                if (!empty($receiptIds)) {
                    Receipt::whereIn('id', $receiptIds)->update([
                        'status' => 'approved',
                        'admin_notes' => $validated['admin_note'] ?? null,
                    ]);
                }

                $advance->update([
                    'status'              => $newAdvanceStatus,
                    'outstanding_balance' => $newBalance,
                ]);

                $actionType = 'LIQUIDATION_APPROVED';
            } else {
                // 3-strike workflow: both 'revise' and 'rejected' increment revision_count
                // Admin's choice maps to 'revise' until threshold exceeded, then auto 'rejected' (terminal)
                $currentCount = (int) ($liquidation->revision_count ?? 0);
                $newCount = $currentCount + 1;
                $requestedStatus = $validated['status']; // 'revise' or 'rejected'
                $finalStatus = $newCount > 3 ? 'rejected' : 'revise';
                $isTerminal = $finalStatus === 'rejected';

                $liquidation->update([
                    'status'         => $finalStatus,
                    'revision_count' => $newCount,
                    'admin_note'     => $validated['admin_note'],
                ]);

                if (!empty($receiptIds)) {
                    Receipt::whereIn('id', $receiptIds)->update([
                        'status' => $finalStatus,
                        'admin_notes' => $validated['admin_note'],
                    ]);
                }

                // Balance unchanged on revise/rejection — no payment was accepted.
                // The cash advance returns to incomplete state.
                $advance->update(['status' => 'incomplete']);

                $actionType = $isTerminal ? 'LIQUIDATION_REJECTED' : 'LIQUIDATION_REVISED';
            }

            // Record status history for cash advance
            \App\Modules\CashAdvances\Models\CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => 'under-review',
                'to_status' => $advance->status,
                'changed_by' => $user->id,
            ]);

            // Audit Log
            AuditLogService::log(
                actorId: $user->id,
                actorRole: $user->role,
                actionType: $actionType,
                entityType: 'liquidation',
                entityId: $liquidation->id,
                beforeState: $beforeState,
                afterState: $liquidation->fresh()->toArray(),
                ipAddress: $request->ip()
            );

            return response()->json([
                'message' => 'Liquidation settlement audit complete.',
                'data' => $liquidation,
            ]);
        });
    }

    /**
     * Update a pending liquidation settlement (employee self-edit).
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $liquidation = Liquidation::findOrFail($id);

        if ($liquidation->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You do not own this liquidation.'], 403);
        }

        if ($liquidation->status === 'rejected') {
            return response()->json(['message' => 'Rejected liquidations (exceeded revision limit) cannot be edited.'], 409);
        }

        if (!in_array($liquidation->status, ['pending', 'revise'])) {
            return response()->json(['message' => 'Only pending or revise liquidations can be edited.'], 409);
        }

        $validated = $request->validate([
            'receipts' => 'sometimes',
            'report_attachment' => 'nullable|file|mimes:jpeg,png,pdf,doc,docx|max:5120',
            'total_expense_amount' => 'sometimes|numeric|min:0.00',
            'shortfall_explanation' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($user, $liquidation, $validated, $request) {
            // Handle receipts update
            if (isset($validated['receipts'])) {
                $receiptsData = is_string($validated['receipts'])
                    ? json_decode($validated['receipts'], true)
                    : $validated['receipts'];

                if (is_array($receiptsData)) {
                    $receiptIds = [];
                    foreach ($receiptsData as $receiptData) {
                        if (!isset($receiptData['id'])) continue;
                        $receipt = Receipt::findOrFail($receiptData['id']);
                        $receipt->update([
                            'vendor_name' => $receiptData['vendor_name'] ?? $receiptData['vendor'] ?? $receipt->vendor_name,
                            'transaction_date' => $receiptData['transaction_date'] ?? $receiptData['date'] ?? $receipt->transaction_date,
                            'total_amount' => $receiptData['total_amount'] ?? $receiptData['amount'] ?? $receipt->total_amount,
                            'vat_amount' => $receiptData['vat_amount'] ?? $receiptData['vat'] ?? $receiptData['tax'] ?? $receipt->vat_amount,
                            'tin' => $receiptData['tin'] ?? $receipt->tin,
                            'invoice_number' => $receiptData['invoice_number'] ?? $receiptData['invoiceNumber'] ?? $receipt->invoice_number,
                            'expense_category_id' => $receiptData['expense_category_id'] ?? $receiptData['categoryId'] ?? $receipt->expense_category_id ?? null,
                            'location' => $receiptData['location'] ?? $receipt->location ?? null,
                            'currency' => $receiptData['currency'] ?? $receipt->currency ?? 'PHP',
                            'vat_classification' => $receiptData['vat_classification'] ?? $receiptData['vatClassification'] ?? $receipt->vat_classification ?? 'vat',
                            'status' => 'pending',
                        ]);

                        if (isset($receiptData['items']) && is_array($receiptData['items'])) {
                            $receipt->items()->delete();
                            foreach ($receiptData['items'] as $item) {
                                if (!empty($item['name']) || isset($item['price'])) {
                                    $receipt->items()->create([
                                        'name' => $item['name'] ?? 'Item',
                                        'qty' => $item['qty'] ?? 1,
                                        'price' => $item['price'] ?? 0,
                                    ]);
                                }
                            }
                        }

                        $receiptIds[] = $receipt->id;
                    }
                    $liquidation->reimbursement_ids = $receiptIds;
                }
            }

            // Handle report attachment
            if ($request->hasFile('report_attachment')) {
                if ($liquidation->report_file_path) {
                    Storage::disk('supabase')->delete($liquidation->report_file_path);
                }
                $liquidation->report_file_path = $request->file('report_attachment')->store('report_letters', 'supabase');
            }

            if (isset($validated['total_expense_amount'])) {
                $liquidation->total_expense_amount = $validated['total_expense_amount'];
            }

            if (array_key_exists('shortfall_explanation', $validated)) {
                $liquidation->shortfall_explanation = $validated['shortfall_explanation'];
            }

            // Reset revise → pending on re-submission (rejected terminal stays rejected)
            if ($liquidation->status === 'revise') {
                $liquidation->status = 'pending';
            }

            $liquidation->save();

            return response()->json([
                'message' => 'Liquidation updated successfully.',
                'data' => $liquidation,
            ]);
        });
    }

    /**
     * Delete a pending liquidation settlement.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $liquidation = Liquidation::findOrFail($id);

        if ($liquidation->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You do not own this liquidation.'], 403);
        }

        if ($liquidation->status !== 'pending') {
            return response()->json(['message' => 'Only pending liquidations can be deleted.'], 409);
        }

        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        // Verify password
        if (!PasswordVerificationService::verify($request, $validated['password'])) {
            throw ValidationException::withMessages([
                'password' => ['Invalid password. Please try again.']
            ]);
        }

        return DB::transaction(function () use ($liquidation) {
            $advance = CashAdvance::findOrFail($liquidation->cash_advance_id);

            // Remove report file from storage
            if ($liquidation->report_file_path) {
                Storage::disk('supabase')->delete($liquidation->report_file_path);
            }

            // Revert cash advance status back to reconcilable state
            $advance->update(['status' => 'incomplete']);

            \App\Modules\CashAdvances\Models\CashAdvanceStatusHistory::create([
                'cash_advance_id' => $advance->id,
                'from_status' => 'under-review',
                'to_status' => 'incomplete',
                'changed_by' => auth()->id(),
            ]);

            $liquidation->delete();

            return response()->json([
                'message' => 'Liquidation deleted successfully.',
            ]);
        });
    }
}
