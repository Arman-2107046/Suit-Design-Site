<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The image health check: each run, and the pictures it found that do not
 * load. Issues are replaced on every run, so the list always shows what is
 * broken now, not what was broken last week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_health_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('checked_count')->default(0);
            $table->unsignedInteger('broken_count')->default(0);
            $table->boolean('cloudflare_checked')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('image_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_health_run_id')->constrained()->cascadeOnDelete();
            $table->string('source', 60)->index();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('field', 60);
            $table->string('label');
            $table->text('url')->nullable();
            $table->string('reason', 30)->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('hidden')->default(false);
            $table->text('edit_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_issues');
        Schema::dropIfExists('image_health_runs');
    }
};
