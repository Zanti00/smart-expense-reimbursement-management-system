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
        $categories = [
            'Meals',
            'Travel',
            'Supplies',
            'Accommodation',
            'Transportation',
            'Others'
        ];

        foreach ($categories as $category) {
            ExpenseCategory::firstOrCreate(['name' => $category]);
        }
    }
}
