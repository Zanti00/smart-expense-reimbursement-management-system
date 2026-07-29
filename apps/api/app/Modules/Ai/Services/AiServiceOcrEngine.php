<?php

namespace App\Modules\Ai\Services;

use App\Modules\Ai\Contracts\AsyncOcrEngineInterface;
use App\Modules\Ai\Exceptions\AiServiceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiServiceOcrEngine implements AsyncOcrEngineInterface
{
    /**
     * POST a receipt dispatch request to the external AI OCR + categorization service.
     *
     * The AI service is responsible for:
     *   1. Downloading the file from the provided file_url (Supabase).
     *   2. Running OCR and expense categorization asynchronously.
     *   3. POSTing results back to the callback_url when complete.
     *
     * @param  int    $receiptId   SERMS receipt ID — echoed back in the AI callback.
     * @param  string $fileUrl     Supabase public URL of the uploaded receipt file.
     * @param  string $callbackUrl SERMS callback endpoint for OCR results.
     * @return void
     *
     * @throws AiServiceException on HTTP error or connectivity failure.
     */
    public function sendForProcessing(int $receiptId, string $fileUrl, string $callbackUrl): void
    {
        $serviceUrl  = rtrim((string) config('services.ai_service.url'), '/');
        $apiKey      = (string) config('services.ai_service.api_key');
        $timeout     = (int)   config('services.ai_service.timeout', 10);

        Log::info('AiServiceOcrEngine: dispatching receipt to AI service.', [
            'receipt_id'   => $receiptId,
            'file_url'     => $fileUrl,
            'callback_url' => $callbackUrl,
        ]);

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post("{$serviceUrl}/api/ocr/process", [
                    'receipt_id'   => $receiptId,
                    'file_url'     => $fileUrl,
                    'callback_url' => $callbackUrl,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new AiServiceException(
                "AI service unreachable: {$e->getMessage()}",
                0,
                $e
            );
        }

        if ($response->failed()) {
            if ($response->status() === 422) {
                $data = $response->json() ?? [];
                Log::warning('AiServiceOcrEngine: quality rejection 422 returned from AI service.', [
                    'receipt_id' => $receiptId,
                    'response'   => $data,
                ]);
                try {
                    app(\App\Modules\Reimbursements\Services\OcrCallbackService::class)->handle($receiptId, [
                        'status'           => 'rejected',
                        'rejection_code'   => $data['rejection_code'] ?? 'blurry',
                        'rejection_reason' => $data['rejection_reason'] ?? $data['message'] ?? 'Image quality is too low for accurate OCR data extraction.',
                        'error'            => $data['message'] ?? 'Image quality is too low for accurate OCR data extraction.',
                    ]);
                } catch (\Throwable $e) {
                    Log::error('AiServiceOcrEngine: failed to apply 422 rejection to receipt: ' . $e->getMessage());
                }
                return;
            }

            throw new AiServiceException(
                "AI service responded with HTTP {$response->status()}: {$response->body()}"
            );
        }

        Log::info('AiServiceOcrEngine: AI service accepted receipt for processing.', [
            'receipt_id' => $receiptId,
            'http_status' => $response->status(),
        ]);
    }
}
