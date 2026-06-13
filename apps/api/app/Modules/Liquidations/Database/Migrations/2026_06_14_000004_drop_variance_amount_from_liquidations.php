<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop variance_amount from liquidations.
     * Variance is now completely derivative of outstanding_balance - total_expense_amount.
     */
    public function up(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            $table->dropColumn('variance_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            $table->decimal('variance_amount', 15, 2)->nullable();
        });
    }
};
