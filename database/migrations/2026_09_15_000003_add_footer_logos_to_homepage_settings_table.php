<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->json('payment_logos')->nullable();
            $table->json('shipping_logos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->dropColumn(['payment_logos', 'shipping_logos']);
        });
    }
};
