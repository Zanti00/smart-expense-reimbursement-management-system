<?php

namespace App\Modules\Reimbursements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReimbursementRequest extends FormRequest
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
            'description' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'cutoff_period' => 'required|string|max:255',
            'report_file' => 'required|file|mimes:pdf,doc,docx|max:2048',
            'receipt_ids' => 'required|array|min:1',
            'receipt_ids.*' => 'exists:receipts,id',
            'receipts' => 'nullable|array',
            'receipts.*.id' => 'required_with:receipts|exists:receipts,id',
            'receipts.*.vendor_name' => 'nullable|string|max:255',
            'receipts.*.transaction_date' => 'nullable|date',
            'receipts.*.total_amount' => 'nullable|numeric|min:0',
            'receipts.*.vat_amount' => 'nullable|numeric|min:0',
            'receipts.*.vat_classification' => 'nullable|string|in:vat,non-vat',
            'receipts.*.tin' => 'nullable|string|max:255',
            'receipts.*.invoice_number' => 'nullable|string|max:255',
            'receipts.*.items' => 'nullable|array',
            'receipts.*.items.*.name' => 'required_with:receipts.*.items|string|max:255',
            'receipts.*.items.*.quantity' => 'required_with:receipts.*.items|integer|min:1',
            'receipts.*.items.*.price' => 'required_with:receipts.*.items|numeric|gt:0',
        ];
    }
}
