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
        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose');
            $table->decimal('amount', 15, 2);
            $table->date('expected_disbursement_date');
            $table->date('expected_liquidation_date');
            $table->enum('status', ['pending', 'approved', 'rejected', 'disbursed', 'liquidated', 'overdue'])->default('pending');
            $table->string('disbursement_channel')->nullable();
            $table->string('disbursement_reference')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_advances');
    }
};
