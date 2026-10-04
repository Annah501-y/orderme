<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_order_financials', function (Blueprint $table): void {
            $table->string('provider_transaction_id')->nullable()->after('provider_reference');
            $table->unsignedInteger('payout_attempts')->default(0)->after('provider_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('seller_order_financials', function (Blueprint $table): void {
            $table->dropColumn(['provider_transaction_id', 'payout_attempts']);
        });
    }
};
