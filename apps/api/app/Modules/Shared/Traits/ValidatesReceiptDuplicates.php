<?php

namespace App\Modules\Shared\Traits;

use App\Modules\Reimbursements\Models\Receipt;
use Illuminate\Validation\ValidationException;

trait ValidatesReceiptDuplicates
{
    /**
     * Checks if a receipt with the given file hash already exists.
     * Throws a ValidationException if a duplicate is detected.
     *
     * @param string $fileHash
     * @throws ValidationException
     */
    protected function validateDuplicateReceipt(string $fileHash): void
    {
        $existingHash = Receipt::where('file_hash', $fileHash)
            ->whereNull('deleted_at')
            ->exists();

        if ($existingHash) {
            throw ValidationException::withMessages([
                'file_hash' => ['Duplicate detected. A receipt with this file hash already exists.']
            ]);
        }
    }
}
