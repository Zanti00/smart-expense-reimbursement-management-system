<?php

namespace App\Modules\CashAdvances\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DisburseCashAdvanceRequest extends FormRequest
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
            'channel' => 'required|string|max:100',
            'reference' => 'required|string|max:100',
            'password' => 'required|string',
        ];
    }
}
