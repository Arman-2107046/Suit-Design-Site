<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('name', 80)->unique();
            $table->string('icon')->nullable();
            $table->text('icon_url')->nullable();
            $table->text('info')->nullable();
            $table->timestamps();
        });

        Schema::create('fabric_fabric_attribute', function (Blueprint $table) {
            $table->foreignId('fabric_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fabric_attribute_id')->constrained()->cascadeOnDelete();
            $table->primary(['fabric_id', 'fabric_attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_fabric_attribute');
        Schema::dropIfExists('fabric_attributes');
    }
};
