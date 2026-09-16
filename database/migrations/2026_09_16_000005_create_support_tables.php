<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 60)->nullable();
            $table->text('contact_address')->nullable();
            $table->string('contact_hours', 160)->nullable();
            $table->text('contact_intro')->nullable();
            $table->string('contact_image')->nullable();
            $table->text('contact_image_url')->nullable();
            $table->text('samples_intro')->nullable();
            $table->unsignedTinyInteger('samples_max')->default(5);
            $table->string('samples_image')->nullable();
            $table->text('samples_image_url')->nullable();
            $table->string('track_image')->nullable();
            $table->text('track_image_url')->nullable();
            $table->string('notify_email')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('subject', 160)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sample_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->json('fabrics');
            $table->json('shipping');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('requested')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('category', 80)->default('General');
            $table->string('question', 255);
            $table->text('answer');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 160);
            $table->string('subtitle', 255)->nullable();
            $table->string('hero_image')->nullable();
            $table->text('hero_image_url')->nullable();
            $table->longText('body')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('pages')->insert([
            ['slug' => 'about-us', 'title' => 'About us', 'subtitle' => 'A small atelier with a simple idea: clothes should fit the person, not the other way round.', 'body' => '<p>Write the story of the house here — where the cloth comes from, who cuts it, and why every suit starts from a body profile rather than a size chart.</p>', 'is_published' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'perfect-fit-guarantee', 'title' => 'Perfect Fit Guarantee', 'subtitle' => 'If it does not fit the way you hoped, we make it right.', 'body' => '<p>Describe the guarantee: what is covered, how a customer reports a fit issue, how alterations or a remake are handled, and the timeframe.</p>', 'is_published' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'terms', 'title' => 'Terms and Conditions', 'subtitle' => null, 'body' => '<p>Your terms of sale go here.</p>', 'is_published' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'subtitle' => null, 'body' => '<p>Your privacy policy goes here.</p>', 'is_published' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('sample_requests');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('site_settings');
    }
};
