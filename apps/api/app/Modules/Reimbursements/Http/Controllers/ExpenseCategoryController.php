<?php

namespace App\Modules\Reimbursements\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reimbursements\Models\ExpenseCategory;

class ExpenseCategoryController extends Controller
{
    /**
     * List all expense categories.
     */
    public function index()
    {
        $categories = ExpenseCategory::orderBy('name')->get();

        return response()->json([
            'data' => $categories,
        ]);
    }
}
