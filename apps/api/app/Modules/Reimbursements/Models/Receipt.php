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
        'expense_category_id',
        'deletion_warning_sent',
        'admin_notes',
        'status',
    ];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        if (!$this->file_path) {
            return null;
        }
        
        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        $baseUrl = config('filesystems.disks.supabase.url');
        if ($baseUrl) {
            return rtrim($baseUrl, '/') . '/' . ltrim($this->file_path, '/');
        }
        return null;
    }

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

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function items()
    {
        return $this->hasMany(ReceiptItem::class, 'receipt_id');
    }

    public function reimbursements()
    {
        return $this->belongsToMany(Reimbursement::class, 'reimbursement_receipts');
    }
}
