<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_advances', 'revision_count')) {
                $table->unsignedInteger('revision_count')->default(0)->after('status');
            }
        });

        if (config('database.default') === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE cash_advances MODIFY COLUMN status ENUM('pending', 'approved', 'revise', 'rejected', 'disbursed', 'signed', 'liquidated', 'overdue', 'settled', 'under-review', 'incomplete') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            if (Schema::hasColumn('cash_advances', 'revision_count')) {
                $table->dropColumn('revision_count');
            }
        });

        if (config('database.default') === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE cash_advances MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'disbursed', 'signed', 'liquidated', 'overdue', 'settled', 'under-review', 'incomplete') NOT NULL DEFAULT 'pending'");
    }
};
