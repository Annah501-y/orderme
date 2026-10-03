<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_destinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('payout_method');
            $table->text('mobile_phone')->nullable();
            $table->string('bank_bic', 20)->nullable();
            $table->text('bank_account_number')->nullable();
            $table->text('bank_account_name')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('payout_destinations');
    }
};
