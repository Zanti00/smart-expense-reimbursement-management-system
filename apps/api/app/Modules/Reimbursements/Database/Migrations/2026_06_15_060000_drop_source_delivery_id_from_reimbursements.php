<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            if (Schema::hasColumn('reimbursements', 'source_delivery_id')) {
                $table->dropUnique('reimbursements_source_delivery_unique');
                $table->dropColumn('source_delivery_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            if (!Schema::hasColumn('reimbursements', 'source_delivery_id')) {
                $table->string('source_delivery_id')->nullable()->after('source_submission_id');
                $table->unique('source_delivery_id', 'reimbursements_source_delivery_unique');
            }
        });
    }
};
