<?php

namespace App\Modules\Reimbursements\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Users\Models\User;

class Receipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uploaded_by',
        'file_path',
        'file_hash',
        'file_type',
        'file_size_bytes',
        'vendor_name',
        'transaction_date',
        'total_amount',
        'vat_amount',
        'tin',
        'invoice_number',
        'vat_classification',
        'ocr_confidence_score',
        'ocr_flagged',
        'is_archived',
        'category',
        'deletion_warning_sent',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'ocr_confidence_score' => 'decimal:2',
        'ocr_flagged' => 'boolean',
        'is_archived' => 'boolean',
        'transaction_date' => 'date',
        'deletion_warning_sent' => 'boolean',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
