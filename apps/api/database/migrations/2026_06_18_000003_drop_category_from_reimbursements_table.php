<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('reimbursements', 'category')) {
            DB::statement("
                UPDATE reimbursements r
                JOIN expense_categories ec ON ec.name = r.category
                SET r.expense_category_id = ec.id
                WHERE r.expense_category_id IS NULL
            ");

            DB::statement("
                UPDATE reimbursements r
                JOIN reimbursement_receipts rr ON rr.reimbursement_id = r.id
                JOIN receipts rc ON rc.id = rr.receipt_id
                SET r.expense_category_id = rc.expense_category_id
                WHERE r.expense_category_id IS NULL
                  AND rc.expense_category_id IS NOT NULL
            ");

            Schema::table('reimbursements', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('reimbursements', 'category')) {
            Schema::table('reimbursements', function (Blueprint $table) {
                $table->string('category')->nullable()->after('description');
            });

            DB::statement("
                UPDATE reimbursements r
                LEFT JOIN expense_categories ec ON ec.id = r.expense_category_id
                SET r.category = COALESCE(ec.name, 'General')
            ");
        }
    }
};
