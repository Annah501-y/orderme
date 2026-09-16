<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('seller_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('status')->default('pending');

            $table->decimal('seller_total', 12, 2);

            $table->timestamps();

            $table->unique(['order_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_orders');
    }
};