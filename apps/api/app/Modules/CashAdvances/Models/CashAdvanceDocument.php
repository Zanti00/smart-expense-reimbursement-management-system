<?php

namespace App\Modules\CashAdvances\Models;

use Illuminate\Database\Eloquent\Model;

class CashAdvanceDocument extends Model
{
    protected $fillable = [
        'cash_advance_id',
        'file_path',
        'file_type',
        'file_name',
    ];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        return \Illuminate\Support\Facades\Storage::disk('supabase')->url($this->file_path);
    }

    public function cashAdvance()
    {
        return $this->belongsTo(CashAdvance::class);
    }
}
