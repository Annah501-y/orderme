<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_financials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('seller_commission_total', 12, 2)
                ->default(0);

            $table->decimal('delivery_commission', 12, 2)
                ->default(0);

            $table->decimal('orderme_commission_total', 12, 2)
                ->default(0);

            $table->decimal('seller_payout_total', 12, 2)
                ->default(0);

            $table->decimal('rider_payout_total', 12, 2)
                ->default(0);

            $table->decimal('delivery_fee', 12, 2)
                ->default(0);

            $table->decimal('order_total', 12, 2)
                ->default(0);

            $table->string('currency', 3)->default('TZS');

            $table->string('status')->default('pending');

            $table->timestamps();

            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_financials');
    }
};
