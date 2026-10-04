<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            // Keep vehicle and licence details with the rider account, not delivery orders.
            $table->string('vehicle_type', 24)->nullable();
            $table->string('license_number', 64)->nullable();
            $table->string('license_document_path')->nullable();
            $table->string('license_status', 24)->default('not_submitted');
            $table->timestamp('license_reviewed_at')->nullable();
            $table->text('license_review_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn([
                'vehicle_type',
                'license_number',
                'license_document_path',
                'license_status',
                'license_reviewed_at',
                'license_review_note',
            ]);
        });
    }
};
