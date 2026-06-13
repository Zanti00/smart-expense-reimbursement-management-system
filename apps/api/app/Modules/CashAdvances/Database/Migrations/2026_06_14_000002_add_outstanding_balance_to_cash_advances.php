<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add outstanding_balance to cash_advances.
     *
     * Initialized at disburse time and decremented on each approved liquidation.
     * Null for advances that have not yet been disbursed.
     */
    public function up(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->decimal('outstanding_balance', 15, 2)->nullable()->after('amount');
        });

        // Seed existing rows with correct balances.
        // SQLite does not support table aliases in UPDATE or GREATEST(), so we branch per driver.
        if (config('database.default') === 'sqlite') {
            DB::statement("
                UPDATE cash_advances
                SET outstanding_balance = CASE
                    WHEN status IN ('pending', 'approved', 'rejected') THEN NULL
                    WHEN status IN ('liquidated', 'settled')           THEN 0
                    ELSE MAX(
                        amount - COALESCE((
                            SELECT SUM(l.total_expense_amount)
                            FROM liquidations l
                            WHERE l.cash_advance_id = cash_advances.id
                              AND l.status = 'liquidated'
                        ), 0),
                        0
                    )
                END
            ");
        } else {
            DB::statement("
                UPDATE cash_advances ca
                SET ca.outstanding_balance = CASE
                    WHEN ca.status IN ('pending', 'approved', 'rejected') THEN NULL
                    WHEN ca.status IN ('liquidated', 'settled')           THEN 0
                    ELSE GREATEST(
                        ca.amount - COALESCE((
                            SELECT SUM(l.total_expense_amount)
                            FROM liquidations l
                            WHERE l.cash_advance_id = ca.id
                              AND l.status = 'liquidated'
                        ), 0),
                        0
                    )
                END
            ");
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropColumn('outstanding_balance');
        });
    }
};
