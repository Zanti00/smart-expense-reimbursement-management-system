<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advance_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_advance_id')->constrained('cash_advances')->cascadeOnDelete();
            $table->foreignId('disbursed_by_id')->constrained('users')->cascadeOnDelete();
            $table->date('disbursement_date');
            $table->string('channel'); // enum in ERD but string provides more flexibility for channels, will keep string
            $table->string('reference_number');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_disbursements');
    }
};
