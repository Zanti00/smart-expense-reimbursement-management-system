<?php

namespace App\Modules\Reimbursements\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    public const DEFAULT_NAMES = [
        'Meals',
        'Travel',
        'Supplies',
        'Accommodation',
        'Transportation',
        'Others',
    ];

    protected $fillable = [
        'name',
    ];

    public static function ensureDefaults(): void
    {
        foreach (self::DEFAULT_NAMES as $category) {
            self::firstOrCreate(['name' => $category]);
        }
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'expense_category_id');
    }

    public function reimbursements()
    {
        return $this->hasMany(Reimbursement::class, 'expense_category_id');
    }
}
