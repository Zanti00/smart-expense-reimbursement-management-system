<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            if (!Schema::hasColumn('reimbursements', 'revision_count')) {
                $table->unsignedInteger('revision_count')->default(0)->after('status');
            }
        });

        // Reimbursements status was converted to VARCHAR in 2026_06_10_094948_expand_reimbursements_table
        // Keep it as VARCHAR to avoid ENUM truncation; VARCHAR safely holds 'revise' without data loss.
        if (config('database.default') !== 'sqlite') {
            // Ensure column is VARCHAR (no-op if already VARCHAR, safe conversion from ENUM if ever reverted)
            DB::statement("ALTER TABLE reimbursements MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            if (Schema::hasColumn('reimbursements', 'revision_count')) {
                $table->dropColumn('revision_count');
            }
        });

        // Keep VARCHAR on rollback as well to avoid re-introducing ENUM truncation
    }
};
