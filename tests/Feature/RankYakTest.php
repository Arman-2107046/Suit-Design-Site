<?php

namespace Tests\Feature;

use App\Filament\Pages\RankYak;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\RankYakSetting;
use App\Support\HtmlSanitizer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class RankYakTest extends TestCase
{
    use RefreshDatabase;

    private RankYakSetting $settings;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();

        $this->settings = RankYakSetting::current();
        $this->settings->update(['enabled' => true]);
    }

    private function article(array $overrides = []): array
    {
        return array_merge([
            'id' => '4821',
            'slug' => 'how-to-choose-a-wedding-suit',
            'excerpt' => 'Choosing a wedding suit starts with the season.',
            'title' => 'How to Choose a Wedding Suit',
            'meta_title' => 'How to Choose a Wedding Suit (2026 Guide)',
            'meta_description' => 'Season, cloth, colour and fit: everything to know before you order a wedding suit.',
            'html' => '<h1>How to Choose a Wedding Suit</h1><p>Choosing a wedding suit starts with the season.</p><h2>Cloth</h2><p>Pick a breathable wool.</p>',
            'markdown' => "# How to Choose a Wedding Suit\n\nChoosing a wedding suit starts with the season.",
            'header_image_url' => 'https://media.rankyak.com/wedding.png',
            'publish_at' => now()->subHour()->toIso8601String(),
            'published_at' => now()->subHour()->toIso8601String(),
        ], $overrides);
    }

    private function webhook(array $payload, ?string $token = null)
    {
        return $this->postJson(route('webhooks.rankyak', $token ?? $this->settings->webhook_token), $payload);
    }

    public function test_the_webhook_turns_an_article_into_a_published_journal_post(): void
    {
        $category = BlogCategory::create(['name' => 'Occasions']);
        $author = Admin::factory()->superAdmin()->create(['name' => 'Arman']);
        $this->settings->update(['blog_category_id' => $category->id, 'admin_id' => $author->id]);

        $this->webhook($this->article())
            ->assertCreated()
            ->assertJson(['ok' => true, 'created' => true, 'status' => 'published', 'url' => route('journal.show', 'how-to-choose-a-wedding-suit')]);

        $post = BlogPost::sole();
        $this->assertSame('rankyak', $post->source);
        $this->assertSame('4821', $post->external_id);
        $this->assertSame('How to Choose a Wedding Suit', $post->title);
        $this->assertSame('how-to-choose-a-wedding-suit', $post->slug);
        $this->assertSame('How to Choose a Wedding Suit (2026 Guide)', $post->seo_title);
        $this->assertStringStartsWith('Season, cloth', $post->seo_description);
        $this->assertStringNotContainsString('<h1>', $post->body, 'the page prints the title itself');
        $this->assertStringContainsString('<h2>Cloth</h2>', $post->body);
        $this->assertSame('https://media.rankyak.com/wedding.png', $post->cover_image_url);
        $this->assertTrue($post->category->is($category));
        $this->assertTrue($post->author->is($author));

        $this->get(route('journal.show', $post->slug))->assertOk();

        $entry = Activity::sole();
        $this->assertSame('imported', $entry->event);
        $this->assertSame('RankYak', $entry->admin_name);
        $this->assertSame('Imported “How to Choose a Wedding Suit” from RankYak', $entry->sentence());
    }

    public function test_a_wrong_token_is_not_found_and_a_switched_off_integration_refuses(): void
    {
        $this->webhook($this->article(), str_repeat('x', 48))->assertNotFound();

        $this->settings->update(['enabled' => false]);
        $this->webhook($this->article())->assertStatus(503);

        $this->assertSame(0, BlogPost::count());
    }

    public function test_the_same_article_sent_again_updates_the_post_instead_of_duplicating_it(): void
    {
        $this->webhook($this->article())->assertCreated();
        $this->webhook($this->article(['title' => 'How to Choose the Perfect Wedding Suit']))->assertOk()->assertJson(['created' => false]);

        $this->assertSame(1, BlogPost::count());
        $this->assertSame('How to Choose the Perfect Wedding Suit', BlogPost::sole()->title);
        $this->assertSame('how-to-choose-a-wedding-suit', BlogPost::sole()->slug, 'the address never changes once live');
    }

    public function test_a_future_publish_date_waits_and_draft_mode_waits_for_the_admin(): void
    {
        $this->webhook($this->article(['id' => 1, 'slug' => 'later', 'publish_at' => now()->addDays(3)->toIso8601String(), 'published_at' => null]));
        $this->assertSame(0, BlogPost::published()->count(), 'scheduled: not live yet');
        $this->get(route('journal.show', 'later'))->assertNotFound();

        $this->settings->update(['publish_mode' => RankYakSetting::DRAFT]);
        $this->webhook($this->article(['id' => 2, 'slug' => 'review-me']));
        $this->assertSame('draft', BlogPost::where('slug', 'review-me')->value('status'));
    }

    public function test_a_slug_another_post_already_uses_gets_a_suffix(): void
    {
        BlogPost::create(['title' => 'Mine', 'slug' => 'how-to-choose-a-wedding-suit', 'body' => '<p>x</p>', 'status' => 'published']);

        $this->webhook($this->article())->assertCreated();

        $this->assertSame('how-to-choose-a-wedding-suit-2', BlogPost::where('source', 'rankyak')->value('slug'));
    }

    public function test_an_article_without_a_title_or_content_is_rejected(): void
    {
        $this->webhook(['id' => '9', 'title' => ''])->assertUnprocessable();

        $this->assertSame(0, BlogPost::count());
        $this->assertStringContainsString('rejected', $this->settings->fresh()->last_error);
    }

    public function test_with_an_api_key_the_live_url_is_reported_back_to_rankyak(): void
    {
        $this->settings->update(['api_key' => 'secret-key']);
        Http::fake(['rankyak.com/api/v1/articles/4821/external-url' => Http::response(['id' => 4821])]);

        $this->webhook($this->article())->assertCreated();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://rankyak.com/api/v1/articles/4821/external-url'
            && $r->hasHeader('X-Api-Key', 'secret-key')
            && $r['external_url'] === route('journal.show', 'how-to-choose-a-wedding-suit'));
        $this->assertNotNull(BlogPost::sole()->external_url_reported_at);
    }

    public function test_sync_imports_missed_articles_skips_unchanged_ones_and_reports_them(): void
    {
        $this->settings->update(['api_key' => 'secret-key']);
        $article = $this->article(['id' => 77, 'slug' => 'missed', 'updated_at' => now()->subDay()->toIso8601String()]);
        Http::fake([
            'rankyak.com/api/v1/articles?*' => Http::response(['data' => [$article], 'links' => ['next' => null]]),
            'rankyak.com/api/v1/articles/77/external-url' => Http::response(['id' => 77]),
        ]);

        $this->artisan('rankyak:sync')->assertSuccessful();
        $this->assertSame(1, BlogPost::count());
        $this->assertNotNull(BlogPost::sole()->external_url_reported_at);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'exclude_h1=true'));

        /* Unchanged on RankYak: an admin's edit here is left alone */
        BlogPost::sole()->update(['title' => 'Edited by us']);
        $this->artisan('rankyak:sync')->assertSuccessful();
        $this->assertSame('Edited by us', BlogPost::sole()->title);
    }

    public function test_article_html_is_sanitised(): void
    {
        $clean = HtmlSanitizer::clean(
            '<p onclick="steal()">Hi <a href="javascript:alert(1)">x</a> <a href="https://ok.test" target="_blank">ok</a></p>'
            .'<script>alert(1)</script><iframe src="https://evil.test"></iframe><style>p{}</style>'
            .'<h1>Inner title</h1><img src="https://img.test/a.png" onerror="x()"><font color="red">kept text</font>'
        );

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('<style', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('<h2>Inner title</h2>', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertStringContainsString('loading="lazy"', $clean);
        $this->assertStringContainsString('kept text', $clean);
        $this->assertStringNotContainsString('<font', $clean);
    }

    public function test_the_admin_page_saves_settings_and_keeps_the_key_secret(): void
    {
        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin');

        Livewire::test(RankYak::class)
            ->assertSee($this->settings->webhookUrl())
            ->fillForm(['enabled' => true, 'api_key' => 'key-123', 'publish_mode' => 'draft'])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = $this->settings->fresh();
        $this->assertSame('key-123', $settings->api_key);
        $this->assertNotSame('key-123', $settings->getRawOriginal('api_key'), 'stored encrypted');
        $this->assertSame('draft', $settings->publish_mode);

        /* Saving again with the key field blank keeps the saved key */
        Livewire::test(RankYak::class)->fillForm(['publish_mode' => 'publish'])->call('save');
        $this->assertSame('key-123', $this->settings->fresh()->api_key);
    }

    public function test_a_new_webhook_url_retires_the_old_one(): void
    {
        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin');
        $old = $this->settings->webhook_token;

        Livewire::test(RankYak::class)->callAction('regenerate');

        $this->webhook($this->article(), $old)->assertNotFound();
        $this->webhook($this->article(), $this->settings->fresh()->webhook_token)->assertCreated();
    }

    public function test_the_page_is_for_super_admins(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        $this->get(RankYak::getUrl())->assertForbidden();
    }
}
