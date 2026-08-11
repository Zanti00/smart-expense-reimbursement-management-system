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
            // SQLite (test runner) does not support ALTER TABLE ... MODIFY.
            // TEXT columns already store the JSON-encoded arrays produced by the
            // model's array casts, so only normalize any legacy scalar values.
            $this->normalizeLegacyScalarValues();
            return;
        }

        // 1. Temporarily widen columns to TEXT to prevent truncation during JSON string conversion
        DB::statement("ALTER TABLE receipts MODIFY file_path TEXT NULL, MODIFY file_hash TEXT NULL, MODIFY file_type TEXT NULL, MODIFY file_size_bytes TEXT NULL");

        // 2. Format any existing plain string data to valid JSON arrays
        $this->normalizeLegacyScalarValues();

        // 3. Convert column types to JSON
        DB::statement("ALTER TABLE receipts MODIFY file_path JSON NULL, MODIFY file_hash JSON NULL, MODIFY file_type JSON NULL, MODIFY file_size_bytes JSON NULL");
    }

    private function normalizeLegacyScalarValues(): void
    {
        $receipts = DB::table('receipts')->get();
        foreach ($receipts as $receipt) {
            $updates = [];
            foreach (['file_path', 'file_hash', 'file_type', 'file_size_bytes'] as $col) {
                $val = $receipt->$col;
                if ($val !== null) {
                    json_decode((string)$val, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $updates[$col] = json_encode([$val]);
                    }
                }
            }
            if (!empty($updates)) {
                DB::table('receipts')->where('id', $receipt->id)->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('file_path', 2048)->nullable()->change();
            $table->string('file_hash')->nullable()->change();
            $table->string('file_type')->nullable()->change();
            $table->bigInteger('file_size_bytes')->nullable()->change();
        });
    }
};
