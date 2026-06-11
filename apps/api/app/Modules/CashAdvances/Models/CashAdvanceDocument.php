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
        if (!$this->file_path) {
            return null;
        }
        $baseUrl = config('filesystems.disks.supabase.url');
        if ($baseUrl) {
            return rtrim($baseUrl, '/') . '/' . ltrim($this->file_path, '/');
        }
        return null;
    }

    public function cashAdvance()
    {
        return $this->belongsTo(CashAdvance::class);
    }
}
