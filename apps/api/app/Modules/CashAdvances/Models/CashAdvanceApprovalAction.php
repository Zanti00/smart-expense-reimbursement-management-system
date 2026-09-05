<?php

namespace App\Modules\CashAdvances\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Users\Models\User;

/**
 * Cash Advance approval audit trail.
 *
 * @property string $action Allowed: 'approved', 'rejected', 'revised'
 *                          'revised' requires migration 2026_05_18_000005 enum patch (adds 'revised').
 *                          Past tense stored; request param uses present tense (revise/reject) mapped in service.
 */
class CashAdvanceApprovalAction extends Model
{
    protected $fillable = [
        'cash_advance_id',
        'approver_id',
        'action',
        'comment',
        'actioned_at',
    ];

    protected $casts = [
        'actioned_at' => 'datetime',
    ];

    public function cashAdvance()
    {
        return $this->belongsTo(CashAdvance::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
