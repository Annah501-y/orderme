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
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 12,2)
                  ->after('total_amount')->default(0);
            $table->decimal('delivery_fee', 12,2)
                   ->after('subtotal')->default(0);
            $table->string('vehicle_type')
                  ->after('delivery_fee')
                  ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal',
                'delivery_fee',
            ]);
    });
    }
};
