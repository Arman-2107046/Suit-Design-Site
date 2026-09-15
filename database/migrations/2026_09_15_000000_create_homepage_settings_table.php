<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_image')->nullable();
            $table->text('hero_image_url')->nullable();
            $table->text('instagram_url')->nullable();
            $table->text('facebook_url')->nullable();
            $table->text('x_url')->nullable();
            $table->text('pinterest_url')->nullable();
            $table->text('tiktok_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_settings');
    }
};
