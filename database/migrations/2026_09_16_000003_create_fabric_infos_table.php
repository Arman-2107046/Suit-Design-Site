<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DETAIL_COLUMNS = [
        'description', 'tone', 'pattern', 'weave', 'brand', 'category', 'seasonality', 'occasions',
        'stretch', 'weight_label', 'weight_gsm', 'composition', 'finish', 'opacity',
    ];

    public function up(): void
    {
        // The per-fabric info card replaces both the attribute library and the fixed detail columns.
        Schema::dropIfExists('fabric_fabric_attribute');
        Schema::dropIfExists('fabric_attributes');

        Schema::table('fabrics', function (Blueprint $table) {
            $table->dropColumn(self::DETAIL_COLUMNS);
        });

        Schema::create('fabric_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->json('badges')->nullable();
            $table->text('column_1')->nullable();
            $table->text('column_2')->nullable();
            $table->text('column_3')->nullable();
            $table->text('column_4')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_infos');

        Schema::table('fabrics', function (Blueprint $table) {
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
    }
};
