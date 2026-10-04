<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add an explicit marker and backfill customer stops for already assigned deliveries. */
    public function up(): void
    {
        Schema::table('deliveries_stops', function (Blueprint $table): void {
            $table->boolean('is_customer_dropoff')->default(false);
        });

        DB::table('deliveries as delivery')
            ->join('orders as customer_order', 'customer_order.id', '=', 'delivery.order_id')
            ->join('addresses as customer_address', 'customer_address.id', '=', 'customer_order.address_id')
            ->select([
                'delivery.id as delivery_id',
                'customer_address.address_line',
                'customer_address.district',
                'customer_address.city',
                'customer_address.region',
                'customer_address.country',
                'customer_address.latitude',
                'customer_address.longitude',
            ])
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('deliveries_stops as existing_stop')
                    ->whereColumn('existing_stop.delivery_id', 'delivery.id')
                    ->where('existing_stop.stop_type', 'delivery')
                    ->whereNull('existing_stop.seller_order_id');
            })
            ->orderBy('delivery.id')
            ->chunkById(100, function (Collection $deliveries): void {
                foreach ($deliveries as $delivery) {
                    $sequence = (int) (DB::table('deliveries_stops')
                        ->where('delivery_id', $delivery->delivery_id)
                        ->max('sequence') ?? 0) + 1;

                    DB::table('deliveries_stops')->insert([
                        'delivery_id' => $delivery->delivery_id,
                        'seller_order_id' => null,
                        'is_customer_dropoff' => true,
                        'stop_type' => 'delivery',
                        'sequence' => $sequence,
                        'address' => collect([
                            $delivery->address_line,
                            $delivery->district,
                            $delivery->city,
                            $delivery->region,
                            $delivery->country,
                        ])->filter()->implode(', '),
                        'latitude' => $delivery->latitude,
                        'longitude' => $delivery->longitude,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'delivery.id', 'delivery_id');
    }

    /** Remove the helper marker while preserving valid drop-off stops already used by deliveries. */
    public function down(): void
    {
        Schema::table('deliveries_stops', function (Blueprint $table): void {
            $table->dropColumn('is_customer_dropoff');
        });
    }
};
