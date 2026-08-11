<?php

namespace App\Modules\Ai\Contracts;

interface OcrEngineInterface
{
    /**
     * Parse uploaded receipt image file paths and extract relevant financial data.
     *
     * @param array $filePaths
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
    public function extractReceiptData(array $filePaths): array;
}
