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
        Schema::table('payout_destinations', function (Blueprint $table): void {
            $table->renameColumn('veriification_sent_at', 'verification_sent_at');
            $table->renameColumn('veriification_expires_at', 'verification_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payout_destinations', function (Blueprint $table): void {
            $table->renameColumn('verification_sent_at', 'veriification_sent_at');
            $table->renameColumn('verification_expires_at', 'veriification_expires_at');
        });
    }
};
