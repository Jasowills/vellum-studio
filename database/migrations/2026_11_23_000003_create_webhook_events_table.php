<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_id', 100)->unique();
            $table->string('type', 50);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->unsignedSmallInteger('delivery_attempt')->nullable();
            $table->unsignedBigInteger('signature_timestamp')->nullable();
            $table->unsignedSmallInteger('processing_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
