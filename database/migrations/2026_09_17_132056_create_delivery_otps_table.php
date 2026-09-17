<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_otps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_id')
                ->constrained('deliveries')
                ->cascadeOnDelete();

            $table->string('otp_hash');

            $table->timestamp('expires_at');

            $table->timestamp('verified_at')->nullable();

            $table->unsignedInteger('attempts')->default(0);

            $table->unsignedInteger('max_attempts')->default(3);

            $table->timestamps();

            $table->index(['delivery_id', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_otps');
    }
};