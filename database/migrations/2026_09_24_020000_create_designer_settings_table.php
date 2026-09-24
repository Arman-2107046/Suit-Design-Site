<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * How the designer lays its options out, so the shop can be tuned without a
 * deploy. The defaults are what the designer already did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedTinyInteger('fabric_columns')->default(3);

            $table->string('style_layout')->default('grid');
            $table->unsignedTinyInteger('style_columns')->default(3);

            $table->unsignedTinyInteger('lining_columns')->default(2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_settings');
    }
};
