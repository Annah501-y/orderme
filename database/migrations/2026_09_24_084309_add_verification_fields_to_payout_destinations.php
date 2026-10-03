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
        Schema::table('payout_destinations', function (Blueprint $table) {
            $table->text('verification_code_hash')->nullable();
            $table->timestamp('veriification_sent_at')->nullable();
            $table->timestamp('veriification_expires_at')->nullable();
            $table->unsignedTinyInteger('verification_attempts')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payout_destinations', function (Blueprint $table) {
            $table->dropColumn([
                'verification_code_hash',
                'verification_sent_at',
                'verification_expires_at',
                'verification_attempts',
            ]);
        });
    }
};
