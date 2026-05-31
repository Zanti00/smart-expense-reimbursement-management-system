<?php

namespace App\Modules\Reimbursements\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptItem extends Model
{
    protected $fillable = [
        'receipt_id',
        'name',
        'quantity',
        'price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
    ];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class, 'receipt_id');
    }
}
