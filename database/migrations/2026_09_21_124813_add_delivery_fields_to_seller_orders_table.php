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
        Schema::table('seller_orders', function (Blueprint $table) {
         $table->decimal('distance_km', 10,2)->nullable()
              ->after('seller_total');
         $table->unsignedInteger('duration_minutes')
              ->nullable()
              ->after('distance_km');
        $table->decimal('delivery_fee', 12, 2)
              ->default(0)
              ->after('duration_minutes');     
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn([
                'distance_km',
                'duration_minutes',
                'delivery_fee'
            ]);
        });
    }
};
