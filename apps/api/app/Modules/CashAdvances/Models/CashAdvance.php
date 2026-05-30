<?php

namespace App\Modules\CashAdvances\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Users\Models\User;

class CashAdvance extends Model
{
    protected $fillable = [
        'user_id',
        'purpose',
        'amount',
        'expected_disbursement_date',
        'expected_liquidation_date',
        'status',
        'signature',
        'acknowledged_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expected_disbursement_date' => 'date',
        'expected_liquidation_date' => 'date',
        'acknowledged_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function document()
    {
        return $this->hasOne(CashAdvanceDocument::class);
    }

    public function approvalActions()
    {
        return $this->hasMany(CashAdvanceApprovalAction::class);
    }

    public function disbursement()
    {
        return $this->hasOne(CashAdvanceDisbursement::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(CashAdvanceStatusHistory::class);
    }
}
