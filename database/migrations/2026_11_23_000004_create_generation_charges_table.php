<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('input_text_tokens');
            $table->unsignedInteger('input_image_tokens');
            $table->unsignedInteger('output_tokens');
            $table->unsignedInteger('reasoning_tokens');
            $table->unsignedInteger('total_tokens');
            $table->unsignedBigInteger('provider_cost_nanos');
            $table->unsignedBigInteger('customer_charge_nanos');
            $table->json('rates_snapshot');
            $table->timestamp('settled_at');
            $table->timestamps();

            $table->index(['user_id', 'settled_at']);
            $table->index('settled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_charges');
    }
};
