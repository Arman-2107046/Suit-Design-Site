<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->boolean('is_new')->default(false)->after('is_default');
            $table->text('description')->nullable();
            $table->string('tone', 60)->nullable();
            $table->string('pattern', 60)->nullable();
            $table->string('weave', 60)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('category', 40)->nullable();
            $table->string('seasonality', 40)->nullable();
            $table->json('occasions')->nullable();
            $table->string('stretch', 40)->nullable();
            $table->string('weight_label', 40)->nullable();
            $table->unsignedSmallInteger('weight_gsm')->nullable();
            $table->string('composition', 120)->nullable();
            $table->string('finish', 40)->nullable();
            $table->string('opacity', 40)->nullable();
        });

        Schema::create('fabric_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('preview')->index();
            $table->text('url');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_images');

        Schema::table('fabrics', function (Blueprint $table) {
            $table->dropColumn([
                'is_new', 'description', 'tone', 'pattern', 'weave', 'brand', 'category', 'seasonality', 'occasions',
                'stretch', 'weight_label', 'weight_gsm', 'composition', 'finish', 'opacity',
            ]);
        });
    }
};
