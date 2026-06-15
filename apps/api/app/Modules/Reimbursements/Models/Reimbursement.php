<?php

namespace App\Modules\Reimbursements\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Users\Models\User;

class Reimbursement extends Model
{
    protected $fillable = [
        'user_id',
        'receipt_id',
        'description',
        'category',
        'amount',
        'date',
        'status',
        'rejection_comment',
        'cutoff_period',
        'report_file_path',
        'admin_notes',
        'submitted_by_name',
        'source_system',
        'source_submission_id',
        'is_request',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_request' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function receipts()
    {
        return $this->belongsToMany(Receipt::class, 'reimbursement_receipts');
    }
}
