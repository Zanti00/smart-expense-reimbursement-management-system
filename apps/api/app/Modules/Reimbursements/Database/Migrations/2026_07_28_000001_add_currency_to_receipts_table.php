<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the ISO 4217 currency code column to the receipts table.
 *
 * Design decisions:
 * - Nullable: NULL means "not yet determined" (OCR still running or locale
 *   detection failed). Setting a default of 'PHP' would silently mislabel
 *   every historical receipt and every receipt whose OCR could not resolve
 *   the locale — both worse than an honest NULL.
 * - char(3): ISO 4217 codes are always exactly 3 uppercase letters. Using
 *   char instead of varchar signals the fixed-width intent to the query planner.
 * - Placed after vat_classification so financial fields stay grouped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->after('vat_classification');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
