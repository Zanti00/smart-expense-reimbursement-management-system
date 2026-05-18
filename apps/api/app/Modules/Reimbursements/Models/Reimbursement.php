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
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }
}
