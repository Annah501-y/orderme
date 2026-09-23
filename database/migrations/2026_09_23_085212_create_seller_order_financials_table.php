<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_order_financials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seller_order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('seller_total', 12, 2);

            $table->decimal('commission_rate', 5, 4)
                ->default(0.0100);

            $table->decimal('commission_amount', 12, 2)
                ->default(0);

            $table->decimal('seller_payout_amount', 12, 2)
                ->default(0);

            $table->string('payout_status')
                ->default('pending');

            $table->string('provider_reference')
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            $table->text('failure_reason')
                ->nullable();

            $table->timestamps();

            $table->unique('seller_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_order_financials');
    }
};
