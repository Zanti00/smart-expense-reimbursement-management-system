<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add outstanding_balance to liquidations table.
     * This snapshots the cash advance's outstanding balance at the time of the liquidation.
     */
    public function up(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            $table->decimal('outstanding_balance', 15, 2)->nullable()->after('total_expense_amount');
        });

        // Seed existing rows: since we don't have historical balance snapshots,
        // we'll backfill using the cash advance's original amount.
        DB::statement("
            UPDATE liquidations
            SET outstanding_balance = (
                SELECT amount
                FROM cash_advances
                WHERE cash_advances.id = liquidations.cash_advance_id
            )
        ");

        // Now fix the variance amount so it is mathematically correct based on the snapshot
        DB::statement("
            UPDATE liquidations
            SET variance_amount = outstanding_balance - total_expense_amount
        ");
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            $table->dropColumn('outstanding_balance');
        });
        
        // Optionally revert variance_amount back to using advance amount
        DB::statement("
            UPDATE liquidations
            SET variance_amount = (
                SELECT amount
                FROM cash_advances
                WHERE cash_advances.id = liquidations.cash_advance_id
            ) - total_expense_amount
        ");
    }
};
