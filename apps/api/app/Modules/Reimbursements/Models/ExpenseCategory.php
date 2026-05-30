<?php

namespace App\Modules\Reimbursements\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = [
        'name',
    ];

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'expense_category_id');
    }
}
