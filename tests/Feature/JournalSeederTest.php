<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Admin;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Fabric;
use Database\Seeders\JournalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_fifty_published_posts_across_six_categories_and_can_run_again(): void
    {
        $author = Admin::factory()->superAdmin()->create();
        foreach (['Navy Twill', 'Fabric12', 'Brown Stripe'] as $name) {
            Fabric::create(['name' => $name, 'price' => 200, 'image' => "https://imagedelivery.net/x/{$name}/public", 'is_default' => false, 'status' => true]);
        }

        $this->seed(JournalSeeder::class);
        $this->seed(JournalSeeder::class);   // updates in place, no duplicates

        $this->assertSame(50, BlogPost::count());
        $this->assertSame(6, BlogCategory::count());
        $this->assertSame(50, BlogPost::published()->count());
        $this->assertTrue(BlogCategory::withCount('posts')->get()->every(fn ($c) => $c->posts_count >= 7));

        $post = BlogPost::where('slug', 'the-first-suit-where-to-start')->sole();
        $this->assertTrue($post->author->is($author));
        $this->assertStringStartsWith('https://imagedelivery.net/x/', $post->cover_image_url);
        $this->assertGreaterThanOrEqual(1, $post->fabrics()->count());
        $this->assertStringContainsString('<h2>', $post->body);

        $this->assertSame(0, Activity::count(), 'seeding is not an admin action');

        $this->get('/journal')->assertOk();
        $this->get("/journal/{$post->slug}")->assertOk();
    }
}
