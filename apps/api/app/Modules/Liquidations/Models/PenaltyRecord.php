<?php

namespace App\Modules\Liquidations\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\CashAdvances\Models\CashAdvance;

class PenaltyRecord extends Model
{
    protected $table = 'penalties';

    protected $fillable = [
        'cash_advance_id',
        'days_overdue',
        'penalty_amount',
    ];

    protected $casts = [
        'days_overdue' => 'integer',
        'penalty_amount' => 'decimal:2',
    ];

    /**
     * Prevent mutating days_overdue once written (clinical policy enforcement).
     */
    protected static function booted()
    {
        static::updating(function ($penalty) {
            if ($penalty->isDirty('days_overdue')) {
                throw new \Exception('Immutable breach: modifying days_overdue is strictly prohibited.');
            }
        });
    }

    public function cashAdvance()
    {
        return $this->belongsTo(CashAdvance::class);
    }
}
