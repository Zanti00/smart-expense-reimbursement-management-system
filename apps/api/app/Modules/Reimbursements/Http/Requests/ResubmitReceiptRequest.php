<?php

namespace App\Modules\Reimbursements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResubmitReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('items')) {
            $this->merge([
                'items' => is_string($this->items) ? json_decode($this->items, true) : $this->items,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'file' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:10240',
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'vendor_name' => 'nullable|string|max:255',
            'transaction_date' => 'nullable|date',
            'total_amount' => 'nullable|numeric|min:0',
            'vat_amount' => 'nullable|numeric|min:0',
            'tin' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'vat_classification' => 'nullable|in:vat,non-vat,VAT,Non-VAT',
            'items' => 'nullable|array',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.price' => 'required_with:items|numeric|gt:0',
        ];
    }
}
