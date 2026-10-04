<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_delivery_financials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('delivery_fee', 12, 2);
            $table->decimal('commission_rate', 5, 4)->default(0.0025);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('rider_payout_amount', 12, 2)->default(0);
            $table->string('payout_status')->default('pending');
            $table->string('provider_reference')->nullable()->unique();
            $table->string('provider_transaction_id')->nullable();
            $table->unsignedInteger('payout_attempts')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_delivery_financials');
    }
};
