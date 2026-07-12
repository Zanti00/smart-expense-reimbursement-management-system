<?php

namespace App\Modules\Ai\Contracts;

interface AsyncOcrEngineInterface
{
    /**
     * Dispatch a receipt to the external AI service for async OCR + categorization.
     *
     * SERMS sends the receipt metadata to the AI service. The AI service downloads
     * the file from the provided URL, processes it, and POSTs results back to the
     * callback URL asynchronously.
     *
     * @param  int    $receiptId   The SERMS receipt ID (echoed back in the callback).
     * @param  string $fileUrl     Publicly accessible Supabase URL of the receipt file.
     * @param  string $callbackUrl The SERMS endpoint the AI service must POST results to.
     * @return void
     *
     * @throws \App\Modules\Ai\Exceptions\AiServiceException on HTTP or connectivity failure.
     *
     * Usage example:
     *   $engine->sendForProcessing(42, 'https://supabase.io/.../receipt.jpg', 'https://serms.app/api/reimbursements/receipts/42/ocr-callback');
     */
    public function sendForProcessing(int $receiptId, string $fileUrl, string $callbackUrl): void;
}
