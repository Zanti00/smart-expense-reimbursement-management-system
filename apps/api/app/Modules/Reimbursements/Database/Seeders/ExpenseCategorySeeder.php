<?php

namespace App\Modules\Reimbursements\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Reimbursements\Models\ExpenseCategory;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ExpenseCategory::ensureDefaults();
    }
}
