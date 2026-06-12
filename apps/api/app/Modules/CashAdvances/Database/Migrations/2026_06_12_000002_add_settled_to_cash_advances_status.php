<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw query for enum modification as standard Schema alter is complex on SQLite vs MySQL
        if (config('database.default') === 'sqlite') {
            Schema::table('cash_advances', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
            return;
        }

        DB::statement("ALTER TABLE cash_advances MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'disbursed', 'liquidated', 'overdue', 'settled') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            Schema::table('cash_advances', function (Blueprint $table) {
                $table->enum('status', ['pending', 'approved', 'rejected', 'disbursed', 'liquidated', 'overdue'])->default('pending')->change();
            });
            return;
        }

        DB::statement("ALTER TABLE cash_advances MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'disbursed', 'liquidated', 'overdue') NOT NULL DEFAULT 'pending'");
    }
};
