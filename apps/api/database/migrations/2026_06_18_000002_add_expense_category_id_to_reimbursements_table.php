<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('reimbursements', 'expense_category_id')) {
            Schema::table('reimbursements', function (Blueprint $table) {
                $table->foreignId('expense_category_id')
                    ->nullable()
                    ->after('description')
                    ->constrained('expense_categories')
                    ->nullOnDelete();
            });
        } else {
            Schema::table('reimbursements', function (Blueprint $table) {
                $table->foreign('expense_category_id')
                    ->references('id')
                    ->on('expense_categories')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reimbursements', 'expense_category_id')) {
            Schema::table('reimbursements', function (Blueprint $table) {
                $table->dropForeign(['expense_category_id']);
                $table->dropColumn('expense_category_id');
            });
        }
    }
};
