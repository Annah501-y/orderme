<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Payment gateway
            $table->string('provider')->default('clickpesa');

            // mpesa, yas, halopesa, airtel_money,
            // crdb, nmb, card
            $table->string('method');

            $table->decimal('amount', 15, 2);

            $table->string('currency', 3)->default('TZS');

            // pending, processing, paid, failed,
            // cancelled, refunded
            $table->string('status')->default('pending');

            // Gateway transaction/reference IDs
            $table->string('transaction_id')->nullable();
            $table->string('reference')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('transaction_id');
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
