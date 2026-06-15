<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('submitted_by_name');
            $table->string('source_submission_id')->nullable()->after('source_system');
            $table->string('source_delivery_id')->nullable()->after('source_submission_id');

            $table->unique(['source_system', 'source_submission_id'], 'reimbursements_source_submission_unique');
            $table->unique('source_delivery_id', 'reimbursements_source_delivery_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropUnique('reimbursements_source_submission_unique');
            $table->dropUnique('reimbursements_source_delivery_unique');
            $table->dropColumn(['source_system', 'source_submission_id', 'source_delivery_id']);
        });
    }
};
