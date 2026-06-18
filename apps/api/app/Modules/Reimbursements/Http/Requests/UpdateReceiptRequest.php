<?php

namespace App\Modules\Reimbursements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'admin_notes' => 'nullable|string',
            'status' => 'nullable|string|in:pending,approved,rejected',
            'total_amount' => 'nullable|numeric|min:0',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_classification' => 'nullable|string|in:vat,non-vat',
        ];
    }
}
