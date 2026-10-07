<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * RankYak writes SEO articles and publishes them here. Its settings live in one
 * row; each imported post remembers which RankYak article it came from, so a
 * re-sent or rewritten article updates the same post instead of duplicating it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rankyak_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('webhook_token', 64);
            $table->text('api_key')->nullable();               // encrypted
            $table->string('publish_mode', 20)->default('publish');
            $table->foreignId('blog_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->boolean('copy_images')->default(true);
            $table->boolean('report_urls')->default(true);
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('admin_id');
            $table->string('external_id', 64)->nullable()->after('source');
            $table->timestamp('external_updated_at')->nullable()->after('external_id');
            $table->timestamp('external_url_reported_at')->nullable()->after('external_updated_at');

            $table->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_id']);
            $table->dropColumn(['source', 'external_id', 'external_updated_at', 'external_url_reported_at']);
        });

        Schema::dropIfExists('rankyak_settings');
    }
};
