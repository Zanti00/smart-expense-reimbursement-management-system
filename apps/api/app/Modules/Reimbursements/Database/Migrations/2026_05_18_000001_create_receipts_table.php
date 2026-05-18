<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->char('file_hash', 64);
            $table->enum('file_type', ['jpeg', 'png', 'pdf']);
            $table->unsignedInteger('file_size_bytes');
            $table->string('vendor_name')->nullable();
            $table->date('transaction_date')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('vat_amount', 15, 2)->nullable();
            $table->string('tin')->nullable();
            $table->string('invoice_number')->nullable();
            $table->enum('vat_classification', ['vat', 'non-vat'])->nullable();
            $table->decimal('ocr_confidence_score', 5, 2)->nullable();
            $table->boolean('ocr_flagged')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
