<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('receipts', function (Blueprint $table) {
                $table->string('file_path')->nullable()->change();
                $table->char('file_hash', 64)->nullable()->change();
                $table->string('file_type')->nullable()->change();
                $table->unsignedInteger('file_size_bytes')->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE receipts MODIFY file_path VARCHAR(255) NULL');
            DB::statement('ALTER TABLE receipts MODIFY file_hash CHAR(64) NULL');
            DB::statement("ALTER TABLE receipts MODIFY file_type ENUM('jpeg', 'png', 'pdf') NULL");
            DB::statement('ALTER TABLE receipts MODIFY file_size_bytes INT UNSIGNED NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('receipts', function (Blueprint $table) {
                $table->string('file_path')->nullable(false)->change();
                $table->char('file_hash', 64)->nullable(false)->change();
                $table->string('file_type')->nullable(false)->change();
                $table->unsignedInteger('file_size_bytes')->nullable(false)->change();
            });
        } else {
            DB::statement('ALTER TABLE receipts MODIFY file_path VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE receipts MODIFY file_hash CHAR(64) NOT NULL');
            DB::statement("ALTER TABLE receipts MODIFY file_type ENUM('jpeg', 'png', 'pdf') NOT NULL");
            DB::statement('ALTER TABLE receipts MODIFY file_size_bytes INT UNSIGNED NOT NULL');
        }
    }
};
