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
    public function handle(): void
    {
        Log::info("Processing OCR for receipt ID: " . $this->receipt->id);

        if (!$this->receipt->file_path) {
            return;
        }

        try {
            // Download the file to a temporary local path because Tesseract needs a local file
            $fileContent = Storage::disk('supabase')->get($this->receipt->file_path);
            $tempPath = tempnam(sys_get_temp_dir(), 'ocr_') . '.' . pathinfo($this->receipt->file_path, PATHINFO_EXTENSION);
            file_put_contents($tempPath, $fileContent);

            $ocr = new TesseractOCR($tempPath);
            $text = $ocr->run();

            $extractedData = $this->extractDataFromText($text);

            $this->receipt->update([
                'vendor_name' => $extractedData['vendor_name'] ?? $this->receipt->vendor_name,
                'transaction_date' => $extractedData['transaction_date'] ?? $this->receipt->transaction_date,
                'total_amount' => $extractedData['total_amount'] ?? $this->receipt->total_amount,
                'vat_amount' => $extractedData['vat_amount'] ?? $this->receipt->vat_amount,
                'tin' => $extractedData['tin'] ?? $this->receipt->tin,
                'invoice_number' => $extractedData['invoice_number'] ?? $this->receipt->invoice_number,
                'ocr_confidence_score' => $extractedData['confidence'],
                'ocr_flagged' => $extractedData['confidence'] < 0.80,
            ]);

            Log::info("OCR processed for receipt ID: " . $this->receipt->id);

            @unlink($tempPath);
        } catch (\Exception $e) {
            Log::error("OCR processing failed for receipt ID: " . $this->receipt->id . " Error: " . $e->getMessage());
        }
    }

    protected function extractDataFromText(string $text): array
    {
        $data = [
            'vendor_name' => null,
            'transaction_date' => null,
            'total_amount' => null,
            'vat_amount' => null,
            'tin' => null,
            'invoice_number' => null,
            'confidence' => 0.85, 
        ];

        if (preg_match('/TIN[\s:]*([0-9\-]+)/i', $text, $matches)) {
            $data['tin'] = $matches[1];
        }

        if (preg_match('/(?:TOTAL|AMOUNT)[\s:]*([0-9,\.]+)/i', $text, $matches)) {
            $val = str_replace(',', '', $matches[1]);
            if (is_numeric($val)) {
                $data['total_amount'] = (float)$val;
            }
        }

        $lines = explode("\n", trim($text));
        if (count($lines) > 0 && !empty(trim($lines[0]))) {
            $data['vendor_name'] = substr(trim($lines[0]), 0, 255);
        }

        if (preg_match('/(\d{2,4}[\/\-]\d{1,2}[\/\-]\d{1,2})/', $text, $matches)) {
            try {
                $data['transaction_date'] = \Carbon\Carbon::parse($matches[1])->format('Y-m-d');
            } catch (\Exception $e) {
                // ignore
            }
        }

        return $data;
    }
}
