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
        'disbursement_channel',
        'disbursement_reference',
        'disbursed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expected_disbursement_date' => 'date',
        'expected_liquidation_date' => 'date',
        'disbursed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
