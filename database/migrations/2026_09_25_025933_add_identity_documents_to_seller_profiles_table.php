<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table): void {
            $table->text('nida_number')->nullable();
            $table->text('tin_reference')->nullable();
            $table->string('business_license_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'nida_number',
                'tin_reference',
                'business_license_path',
            ]);
        });
    }
};
