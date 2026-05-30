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
        DB::statement('ALTER TABLE receipts MODIFY file_path VARCHAR(255) NULL');
        DB::statement('ALTER TABLE receipts MODIFY file_hash CHAR(64) NULL');
        DB::statement("ALTER TABLE receipts MODIFY file_type ENUM('jpeg', 'png', 'pdf') NULL");
        DB::statement('ALTER TABLE receipts MODIFY file_size_bytes INT UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE receipts MODIFY file_path VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE receipts MODIFY file_hash CHAR(64) NOT NULL');
        DB::statement("ALTER TABLE receipts MODIFY file_type ENUM('jpeg', 'png', 'pdf') NOT NULL");
        DB::statement('ALTER TABLE receipts MODIFY file_size_bytes INT UNSIGNED NOT NULL');
    }
};
