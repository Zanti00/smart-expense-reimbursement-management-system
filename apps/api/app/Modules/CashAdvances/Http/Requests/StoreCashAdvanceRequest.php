<?php

namespace App\Modules\CashAdvances\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashAdvanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // handled by Policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'purpose' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expected_disbursement_date' => 'required|date|after_or_equal:today',
            'expected_liquidation_date' => 'required|date|after:expected_disbursement_date',
            'documents' => 'required|array|max:5',
            'documents.*' => 'file|max:2048|mimes:pdf,doc,docx,xlsx,jpeg,png,jpg',
        ];
    }
}
