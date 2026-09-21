<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Fabric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    private function story(array $overrides = []): BlogPost
    {
        $author = User::factory()->create(['name' => 'Nadia Karim']);
        $category = BlogCategory::firstOrCreate(['slug' => 'style'], ['name' => 'Style']);

        return BlogPost::create(array_merge([
            'user_id' => $author->id,
            'blog_category_id' => $category->id,
            'title' => 'How a suit should fit at the shoulder',
            'body' => '<p>' . str_repeat('The shoulder seam should sit where your shoulder ends. ', 60) . '</p>',
            'status' => 'published',
            'tags' => ['Fit', 'Style'],
        ], $overrides));
    }

    public function test_published_posts_are_listed_and_readable_with_computed_fields(): void
    {
        $post = $this->story();

        $this->assertSame('how-a-suit-should-fit-at-the-shoulder', $post->slug);
        $this->assertSame(3, $post->reading_minutes);
        $this->assertNotNull($post->published_at);

        $this->get('/journal')->assertOk()->assertInertia(fn ($p) => $p->component('Journal/Index')->where('featured.slug', $post->slug)->has('categories', 1));
        $this->get("/journal/{$post->slug}")->assertOk()->assertInertia(fn ($p) => $p->component('Journal/Show')->where('post.author', 'Nadia Karim')->where('post.liked', false));
        $this->assertSame(1, $post->fresh()->views);
        $this->get('/journal/feed')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')->assertSee($post->title);
    }

    public function test_drafts_and_scheduled_posts_stay_hidden(): void
    {
        $draft = $this->story(['status' => 'draft', 'slug' => 'draft']);
        $scheduled = $this->story(['slug' => 'later', 'published_at' => now()->addDay(), 'title' => 'Later']);

        $this->get('/journal')->assertInertia(fn ($p) => $p->where('featured', null)->has('posts.data', 0));
        $this->get('/journal/draft')->assertNotFound();
        $this->get('/journal/later')->assertNotFound();
        $this->assertSame('draft', $draft->status);
        $this->assertTrue($scheduled->published_at->isFuture());
    }

    public function test_comments_wait_for_approval_and_hidden_ones_disappear(): void
    {
        $post = $this->story();

        $this->post("/journal/{$post->slug}/comments", ['name' => 'Alex', 'email' => 'Alex@Example.test', 'body' => 'Great advice on shoulders.', 'website' => ''])
            ->assertRedirect()->assertSessionHas('comment_sent', true);

        $comment = BlogComment::first();
        $this->assertSame('pending', $comment->status);
        $this->assertSame('alex@example.test', $comment->email);
        $this->get("/journal/{$post->slug}")->assertInertia(fn ($p) => $p->has('post.comments', 0));

        $comment->update(['status' => 'approved']);
        $this->get("/journal/{$post->slug}")->assertInertia(fn ($p) => $p->has('post.comments', 1)->where('post.comments.0.name', 'Alex'));

        $comment->update(['status' => 'hidden']);
        $this->get("/journal/{$post->slug}")->assertInertia(fn ($p) => $p->has('post.comments', 0));

        // Bots fill the hidden field.
        $this->post("/journal/{$post->slug}/comments", ['name' => 'Bot', 'email' => 'bot@example.test', 'body' => 'Buy things', 'website' => 'http://spam'])
            ->assertSessionHasErrors('website');
        $this->assertSame(1, BlogComment::count());
    }

    public function test_signed_in_users_comment_under_their_own_name(): void
    {
        $post = $this->story();
        $user = User::factory()->create(['name' => 'Priya Sen', 'email' => 'priya@example.test']);

        $this->actingAs($user)->post("/journal/{$post->slug}/comments", ['name' => 'ignored', 'email' => 'ignored@example.test', 'body' => 'Lovely piece.']);

        $comment = BlogComment::first();
        $this->assertSame('Priya Sen', $comment->name);
        $this->assertSame('priya@example.test', $comment->email);
        $this->assertSame($user->id, $comment->user_id);
    }

    public function test_likes_toggle_once_per_reader(): void
    {
        $post = $this->story();

        // A first-time reader gets a long-lived identity cookie with their like.
        $first = $this->postJson("/journal/{$post->slug}/like")->assertOk()->assertJson(['liked' => true, 'count' => 1]);
        $this->assertNotNull(collect($first->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'ct_reader'));

        // The same reader (same cookie) toggles rather than double counting.
        // (JSON test requests only carry cookies with withCredentials(); browsers send them with credentials: "same-origin".)
        $this->withCredentials()->withCookie('ct_reader', 'reader-abc')->postJson("/journal/{$post->slug}/like")->assertJson(['liked' => true, 'count' => 2]);
        $this->withCredentials()->withCookie('ct_reader', 'reader-abc')->postJson("/journal/{$post->slug}/like")->assertJson(['liked' => false, 'count' => 1]);

        $user = User::factory()->create();
        $this->actingAs($user)->postJson("/journal/{$post->slug}/like")->assertJson(['liked' => true, 'count' => 2]);
        $this->actingAs($user)->get("/journal/{$post->slug}")->assertInertia(fn ($p) => $p->where('post.liked', true)->where('post.likes', 2));
    }

    public function test_shop_the_cloths_and_homepage_stories(): void
    {
        $fabric = Fabric::create(['name' => 'Navy Twill', 'price' => 249, 'image' => 'https://x/s.png', 'is_default' => true, 'status' => true]);
        $post = $this->story();
        $post->fabrics()->attach($fabric);

        $this->get("/journal/{$post->slug}")->assertInertia(fn ($p) => $p->has('post.fabrics', 1)->where('post.fabrics.0.name', 'Navy Twill'));
        $this->get('/')->assertInertia(fn ($p) => $p->has('stories', 1)->where('stories.0.slug', $post->slug));
    }

    public function test_admin_journal_pages_render(): void
    {
        $this->story();
        $this->actingAs(User::factory()->create());

        $this->get('/admin/blog-posts')->assertOk();
        $this->get('/admin/blog-posts/create')->assertOk();
        $this->get('/admin/blog-categories')->assertOk();
        $this->get('/admin/blog-comments')->assertOk();
    }
}
