<?php

namespace App\Modules\Reimbursements\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Modules\Reimbursements\Models\Receipt;
use App\Modules\Ai\Contracts\AsyncOcrEngineInterface;
use App\Modules\Ai\Exceptions\AiServiceException;
use Illuminate\Support\Facades\Log;

class DispatchReceiptToAiService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of queue attempts before the job is considered failed.
     */
    public int $tries = 3;

    /**
     * Exponential backoff delays (seconds) between retries.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(protected Receipt $receipt) {}

    /**
     * Send the receipt to the external AI OCR + categorization service.
     *
     * The AI service downloads the file from Supabase and POSTs results
     * back to our callback endpoint asynchronously.
     */
    public function handle(AsyncOcrEngineInterface $ocrEngine): void
    {
        Log::info('DispatchReceiptToAiService: sending receipt to AI service.', [
            'receipt_id' => $this->receipt->id,
        ]);

        $fileUrl = $this->receipt->file_url;

        if (!$fileUrl) {
            Log::warning('DispatchReceiptToAiService: receipt has no file URL, skipping.', [
                'receipt_id' => $this->receipt->id,
            ]);
            return;
        }

        $callbackUrl = rtrim((string) config('app.url'), '/')
            . "/api/reimbursements/receipts/{$this->receipt->id}/ocr-callback";

        $ocrEngine->sendForProcessing($this->receipt->id, $fileUrl, $callbackUrl);

        Log::info('DispatchReceiptToAiService: dispatch accepted by AI service.', [
            'receipt_id'   => $this->receipt->id,
            'callback_url' => $callbackUrl,
        ]);
    }

    /**
     * Handle final job failure after all retries are exhausted.
     * Marks the receipt as 'failed' so the user/admin can take action.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DispatchReceiptToAiService: all retries exhausted for receipt.', [
            'receipt_id' => $this->receipt->id,
            'error'      => $exception->getMessage(),
        ]);

        $this->receipt->update(['status' => 'failed']);
    }
}
