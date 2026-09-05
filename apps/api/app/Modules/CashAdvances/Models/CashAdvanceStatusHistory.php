<?php

namespace App\Modules\CashAdvances\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Users\Models\User;

class CashAdvanceStatusHistory extends Model
{
    protected $table = 'cash_advance_status_history';

    protected $fillable = [
        'cash_advance_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function cashAdvance()
    {
        return $this->belongsTo(CashAdvance::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
