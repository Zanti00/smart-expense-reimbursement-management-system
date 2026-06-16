<?php

namespace App\Modules\CashAdvances\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation rules for employee self-edit of pending/rejected cash advances.
 */
class UpdateCashAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purpose' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'expected_disbursement_date' => 'sometimes|date',
            'expected_liquidation_date' => 'sometimes|date|after:expected_disbursement_date',
            'documents' => 'sometimes|array|max:5',
            'documents.*' => 'file|mimes:pdf,doc,docx,xlsx,jpeg,png,jpg|max:2048',
        ];
    }
}
