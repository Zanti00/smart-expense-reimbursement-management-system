<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add rejection_code and rejection_reason columns to the receipts table.
 *
 * Captures image quality rejection metadata (e.g. blurry, too_dark, too_small)
 * so SERMS UI can surface clear rejection toasts and quality modals to the user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('rejection_code')->nullable()->after('status');
            $table->text('rejection_reason')->nullable()->after('rejection_code');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn(['rejection_code', 'rejection_reason']);
        });
    }
};
