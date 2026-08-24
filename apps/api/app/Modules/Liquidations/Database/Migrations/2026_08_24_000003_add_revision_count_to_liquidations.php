<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            if (!Schema::hasColumn('liquidations', 'revision_count')) {
                $table->unsignedInteger('revision_count')->default(0)->after('status');
            }
        });

        if (config('database.default') === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE liquidations MODIFY COLUMN status ENUM('pending', 'revise', 'rejected', 'approved', 'liquidated') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('liquidations', function (Blueprint $table) {
            if (Schema::hasColumn('liquidations', 'revision_count')) {
                $table->dropColumn('revision_count');
            }
        });

        if (config('database.default') === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE liquidations MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'liquidated') NOT NULL DEFAULT 'pending'");
    }
};
