<?php

namespace App\Modules\Ai\Contracts;

interface OcrEngineInterface
{
    /**
     * Parse an uploaded receipt image file path and extract relevant financial data.
     *
     * @param string $filePath
     * @return array{
     *     vendor_name: string|null,
     *     transaction_date: string|null,
     *     total_amount: float|null,
     *     vat_amount: float|null,
     *     tin: string|null,
     *     invoice_number: string|null,
     *     ocr_confidence_score: float
     * }
     */
    public function extractReceiptData(string $filePath): array;
}
