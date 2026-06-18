<?php

namespace App\Modules\Liquidations\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Users\Models\User;
use App\Modules\CashAdvances\Models\CashAdvance;

class Liquidation extends Model
{
    protected $fillable = [
        'cash_advance_id',
        'user_id',
        'status',
        'reimbursement_ids',
        'total_expense_amount',
        'outstanding_balance',
        'shortfall_explanation',
        'admin_note',
        'report_file_path',
    ];

    protected $casts = [
        'reimbursement_ids' => 'json',
        'total_expense_amount' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashAdvance()
    {
        return $this->belongsTo(CashAdvance::class);
    }
}
