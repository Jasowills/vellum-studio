<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('provider_generation_id')->nullable()->unique();
            $table->string('idempotency_key', 80)->unique();
            $table->char('request_hash', 64);
            $table->string('status', 16)->default('pending')->index();
            $table->text('prompt');
            $table->string('size', 16);
            $table->string('quality', 16);
            $table->json('reference_images');
            $table->json('metadata')->nullable();

            $table->json('pricing_snapshot');
            $table->json('estimate_snapshot');

            $table->unsignedBigInteger('estimated_provider_cost_min_nanos')->nullable();
            $table->unsignedBigInteger('estimated_provider_cost_max_nanos')->nullable();
            $table->unsignedBigInteger('estimated_customer_charge_min_nanos')->nullable();
            $table->unsignedBigInteger('estimated_customer_charge_max_nanos')->nullable();

            $table->unsignedBigInteger('actual_provider_cost_nanos')->nullable();
            $table->unsignedBigInteger('actual_customer_charge_nanos')->nullable();

            $table->unsignedInteger('input_text_tokens')->nullable();
            $table->unsignedInteger('input_image_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('archived_image_path')->nullable();

            $table->string('failure_code', 80)->nullable();
            $table->text('failure_message')->nullable();
            $table->string('failure_stage', 24)->nullable();
            $table->json('provider_error')->nullable();

            $table->unsignedSmallInteger('submit_attempts')->default(0);
            $table->timestamp('next_submit_at')->nullable();
            $table->unsignedSmallInteger('reconcile_attempts')->default(0);
            $table->timestamp('next_reconcile_at')->nullable();

            $table->timestamp('provider_created_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'next_submit_at']);
            $table->index(['status', 'next_reconcile_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
