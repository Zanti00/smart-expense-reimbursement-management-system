<?php

namespace App\Modules\Reimbursements\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Modules\Reimbursements\Models\Receipt;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Illuminate\Support\Facades\Log;

class ProcessReceiptOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $receipt;

    /**
     * Create a new job instance.
     */
    public function __construct(Receipt $receipt)
    {
        $this->receipt = $receipt;
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Modules\Ai\Contracts\OcrEngineInterface $ocrEngine): void
    {
        Log::info("Processing OCR for receipt ID: " . $this->receipt->id);

        if (!$this->receipt->file_path) {
            return;
        }

        try {
            // Add a simulated delay so the user can observe the "processing" state and polling in the UI
            sleep(5);

            // Temporarily mock OCR data instead of calling Tesseract
            /*
            $fileContent = Storage::disk('supabase')->get($this->receipt->file_path);
            $tempPath = tempnam(sys_get_temp_dir(), 'ocr_') . '.' . pathinfo($this->receipt->file_path, PATHINFO_EXTENSION);
            file_put_contents($tempPath, $fileContent);

            $extractedData = $ocrEngine->extractReceiptData($tempPath);
            */

            $extractedData = [
                'vendor_name' => 'Mock Vendor Corp',
                'transaction_date' => now()->toDateString(),
                'total_amount' => 1500.00,
                'vat_amount' => 180.00,
                'tin' => '123-456-789-000',
                'invoice_number' => 'INV-' . rand(1000, 9999),
                'ocr_confidence_score' => 0.95,
            ];

            $this->receipt->update([
                'vendor_name' => $extractedData['vendor_name'] ?? $this->receipt->vendor_name,
                'transaction_date' => $extractedData['transaction_date'] ?? $this->receipt->transaction_date,
                'total_amount' => $extractedData['total_amount'] ?? $this->receipt->total_amount,
                'vat_amount' => $extractedData['vat_amount'] ?? $this->receipt->vat_amount,
                'tin' => $extractedData['tin'] ?? $this->receipt->tin,
                'invoice_number' => $extractedData['invoice_number'] ?? $this->receipt->invoice_number,
                'ocr_confidence_score' => $extractedData['ocr_confidence_score'],
                'ocr_flagged' => $extractedData['ocr_confidence_score'] < 0.80,
                'status' => 'pending', // Important: update status from processing to pending
            ]);

            Log::info("OCR processed for receipt ID: " . $this->receipt->id);

            // @unlink($tempPath);
        } catch (\Exception $e) {
            Log::error("OCR processing failed for receipt ID: " . $this->receipt->id . " Error: " . $e->getMessage());
        }
    }
}
