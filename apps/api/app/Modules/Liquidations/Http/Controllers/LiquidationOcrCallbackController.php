<?php

namespace App\Modules\Liquidations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reimbursements\Http\Requests\OcrCallbackRequest;
use App\Modules\Reimbursements\Services\OcrCallbackService;
use Illuminate\Http\Request;

class LiquidationOcrCallbackController extends Controller
{
    /**
     * Handle async OCR callback for liquidation receipts.
     *
     * The ocr-pipeline POSTs here with the same contract as reimbursement
     * callbacks (vendor_name, total_amount, tin, etc.). We reuse
     * OcrCallbackService which already handles idempotency, confidence gating,
     * duplicate detection, item sync, and audit logging.
     *
     * Auth is via Bearer AI_SERVICE_API_KEY (auth.ai-service-api middleware).
     */
    public function __invoke(OcrCallbackRequest $request, $id)
    {
        $service = app(OcrCallbackService::class);
        $receipt = $service->handle((int) $id, $request->validated());

        return response()->json([
            'message' => 'OCR callback processed.',
            'data' => $receipt,
        ]);
    }
}
