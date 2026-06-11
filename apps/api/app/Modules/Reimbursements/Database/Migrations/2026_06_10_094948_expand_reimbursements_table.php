<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->string('cutoff_period')->nullable()->after('status');
            $table->string('report_file_path')->nullable()->after('cutoff_period');
            $table->text('admin_notes')->nullable()->after('report_file_path');
            $table->string('submitted_by_name')->nullable()->after('admin_notes');
            
            // Convert status to string to effectively allow the new enum values
            // (SQLite has issues with altering ENUMs, so string change is the safer route)
            $table->string('status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropColumn(['cutoff_period', 'report_file_path', 'admin_notes', 'submitted_by_name']);
        });
    }
};
