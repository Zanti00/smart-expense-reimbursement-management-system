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

        $fileUrls = $this->receipt->file_url;

        if (empty($fileUrls)) {
            Log::warning('DispatchReceiptToAiService: receipt has no file URL, skipping.', [
                'receipt_id' => $this->receipt->id,
            ]);
            return;
        }

        $urlsArray = is_array($fileUrls) ? $fileUrls : [$fileUrls];

        $baseUrl = config('services.ai_service.callback_base_url') ?: config('app.url');
        $callbackUrl = rtrim((string) $baseUrl, '/')
            . "/api/reimbursements/receipts/{$this->receipt->id}/ocr-callback";

        try {
            $ocrEngine->sendForProcessing($this->receipt->id, $urlsArray, $callbackUrl);

            Log::info('DispatchReceiptToAiService: dispatch accepted by AI service.', [
                'receipt_id'   => $this->receipt->id,
                'callback_url' => $callbackUrl,
            ]);
        } catch (AiServiceException $e) {
            // System boundary failure: the external AI OCR service rejected or
            // could not be reached. Log actionable context (including whether the
            // service is even configured) and mark the receipt as failed with a
            // clear reason so the UI can surface it instead of silently stalling.
            Log::error('DispatchReceiptToAiService: AI OCR service call failed.', [
                'receipt_id'            => $this->receipt->id,
                'callback_url'          => $callbackUrl,
                'ai_service_configured' => (bool) config('services.ai_service.url'),
                'error'                 => $e->getMessage(),
            ]);

            $this->receipt->update([
                'status'           => 'failed',
                'ocr_flagged'      => true,
                'rejection_code'   => 'ocr_failed',
                'rejection_reason' => 'OCR service was unreachable or returned an error. Please retry OCR.',
            ]);

            throw $e;
        } catch (\Throwable $e) {
            Log::error('DispatchReceiptToAiService: unexpected error dispatching OCR.', [
                'receipt_id' => $this->receipt->id,
                'callback_url' => $callbackUrl,
                'error'      => $e->getMessage(),
            ]);

            $this->receipt->update([
                'status'           => 'failed',
                'ocr_flagged'      => true,
                'rejection_code'   => 'ocr_failed',
                'rejection_reason' => 'OCR processing could not be started. Please retry OCR.',
            ]);

            throw $e;
        }
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
