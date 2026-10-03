<?php

namespace Tests\Feature;

use App\Models\Fabric;
use App\Models\HomepageSetting;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CloudflareMediaTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.cloudflare.com/client/v4/accounts/acc123/';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudflare.account_id' => 'acc123',
            'services.cloudflare.api_token' => 'test-token',
            'services.cloudflare.images_hash' => 'HASH',
        ]);

        @unlink(storage_path('app/cloudflare-migration.json'));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/cloudflare-migration.json'));

        parent::tearDown();
    }

    private function ok(array $result = []): array
    {
        return ['success' => true, 'errors' => [], 'result' => $result];
    }

    /* ---------------------------------------------------------------- */
    /*  The disk the admin's forms upload through                        */
    /* ---------------------------------------------------------------- */

    public function test_a_form_upload_is_stored_under_its_path_and_served_from_cloudflare(): void
    {
        Http::fake([
            self::API.'images/v1/homepage/hero.png' => Http::response(['success' => false], 404),
            self::API.'images/v1' => Http::response($this->ok(['id' => 'homepage/hero.png'])),
        ]);

        Storage::disk('cloudflare')->put('homepage/hero.png', 'PNG-BYTES');

        Http::assertSent(fn (Request $r) => $r->url() === self::API.'images/v1'
            && $r->method() === 'POST'
            && $r->hasHeader('Authorization', 'Bearer test-token')
            && str_contains($r->body(), 'homepage/hero.png'));

        $this->assertSame(
            'https://imagedelivery.net/HASH/homepage/hero.png/public',
            Storage::disk('cloudflare')->url('homepage/hero.png')
        );
    }

    public function test_writing_over_a_path_replaces_the_image(): void
    {
        Http::fake([
            self::API.'images/v1/homepage/hero.png' => Http::sequence()
                ->push($this->ok(['id' => 'homepage/hero.png']))   // exists
                ->push($this->ok()),                               // deleted
            self::API.'images/v1' => Http::response($this->ok(['id' => 'homepage/hero.png'])),
        ]);

        Storage::disk('cloudflare')->put('homepage/hero.png', 'NEW-BYTES');

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'images/v1/homepage/hero.png'));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::API.'images/v1');
    }

    public function test_deleting_something_already_gone_is_not_an_error(): void
    {
        Http::fake([self::API.'images/v1/*' => Http::response(['success' => false], 404)]);

        Storage::disk('cloudflare')->delete('homepage/long-gone.png');

        Http::assertSentCount(1);
    }

    /* ---------------------------------------------------------------- */
    /*  Browser uploads                                                  */
    /* ---------------------------------------------------------------- */

    public function test_the_browser_gets_a_one_time_image_upload_address_never_the_token(): void
    {
        Http::fake([self::API.'images/v2/direct_upload' => Http::response($this->ok([
            'id' => 'abc', 'uploadURL' => 'https://upload.imagedelivery.net/one-time',
        ]))]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.uploads.image'))
            ->assertOk()
            ->assertJsonPath('uploadURL', 'https://upload.imagedelivery.net/one-time');

        $this->assertStringNotContainsString('test-token', $response->getContent());
    }

    public function test_upload_addresses_are_closed_to_guests(): void
    {
        Http::fake();

        $this->postJson(route('admin.uploads.image'))->assertUnauthorized();
        $this->postJson(route('admin.uploads.video'))->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_a_cloudflare_failure_reaches_the_browser_as_a_reason(): void
    {
        Http::fake([self::API.'images/v2/direct_upload' => Http::response([
            'success' => false, 'errors' => [['code' => 10000, 'message' => 'Authentication error']],
        ], 403)]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.uploads.image'))
            ->assertStatus(502)
            ->assertJsonPath('message', 'Cloudflare Images could not mint upload URL: Authentication error');
    }

    public function test_the_video_field_waits_for_stream_to_finish_the_mp4(): void
    {
        $uid = str_repeat('a', 32);
        $mp4 = "https://customer-xyz.cloudflarestream.com/{$uid}/downloads/default.mp4";

        Http::fake([
            self::API."stream/{$uid}" => Http::sequence()
                ->push($this->ok(['readyToStream' => false, 'status' => ['pctComplete' => '40']]))
                ->push($this->ok(['readyToStream' => true])),
            self::API."stream/{$uid}/downloads" => Http::response($this->ok([
                'default' => ['status' => 'ready', 'url' => $mp4, 'percentComplete' => 100],
            ])),
        ]);

        $admin = User::factory()->create();

        $this->actingAs($admin)->getJson(route('admin.uploads.video.status', $uid))
            ->assertOk()->assertJsonPath('state', 'encoding')->assertJsonPath('url', null);

        $this->actingAs($admin)->getJson(route('admin.uploads.video.status', $uid))
            ->assertOk()->assertJsonPath('state', 'ready')->assertJsonPath('url', $mp4);
    }

    public function test_a_video_status_check_only_accepts_a_stream_id(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson('/admin/uploads/video/..%2F..%2Fsecrets')
            ->assertNotFound();

        Http::assertNothingSent();
    }

    /* ---------------------------------------------------------------- */
    /*  Moving what is already on Cloudinary                             */
    /* ---------------------------------------------------------------- */

    private function seedCloudinaryContent(): void
    {
        Fabric::create([
            'name' => 'Navy Twill', 'price' => 200, 'is_default' => true, 'status' => true,
            'image' => 'https://res.cloudinary.com/demo/image/upload/v1790056478/pg2f1lzagzd0nhdzvhzl.png',
        ]);

        HomepageSetting::query()->insert([
            'hero_image' => 'homepage/01HERO.png',
            'hero_image_url' => 'https://res.cloudinary.com/demo/image/upload/v1789442466/homepage/01HERO.png',
            /* stored the way Laravel encodes JSON: escaped slashes */
            'payment_logos' => json_encode([['path' => 'homepage/logos/visa.jpg', 'url' => 'https://res.cloudinary.com/demo/image/upload/v1789442471/homepage/logos/visa.jpg']]),
        ]);
    }

    public function test_a_dry_run_copies_nothing_and_changes_nothing(): void
    {
        Http::fake();
        $this->seedCloudinaryContent();

        $this->artisan('media:move-to-cloudflare')
            ->expectsOutputToContain('3 distinct addresses: 3 images and 0 videos still to copy')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertStringContainsString('res.cloudinary.com', Fabric::first()->image);
    }

    public function test_executing_copies_each_image_under_its_path_and_rewrites_every_address(): void
    {
        Http::fake([self::API.'images/v1' => Http::response($this->ok(['id' => 'x']))]);
        $this->seedCloudinaryContent();

        $this->artisan('media:move-to-cloudflare', ['--execute' => true])
            ->expectsOutputToContain('Nothing points at Cloudinary any more.')
            ->assertSuccessful();

        /* Cloudflare was told to fetch each one itself, keeping its Cloudinary path as the id */
        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'homepage/01HERO.png')
            && str_contains($r->body(), 'https://res.cloudinary.com/demo/image/upload/v1789442466/homepage/01HERO.png'));

        $this->assertSame('https://imagedelivery.net/HASH/pg2f1lzagzd0nhdzvhzl.png/public', Fabric::first()->image);

        $settings = HomepageSetting::first();
        $this->assertSame('https://imagedelivery.net/HASH/homepage/01HERO.png/public', $settings->hero_image_url);

        /* The path a form saved now resolves to the very same image on the new disk */
        $this->assertSame($settings->hero_image_url, Storage::disk('cloudflare')->url($settings->hero_image));

        /* JSON with escaped slashes was rewritten too, and still decodes */
        $this->assertSame('https://imagedelivery.net/HASH/homepage/logos/visa.jpg/public', $settings->payment_logos[0]['url']);
    }

    public function test_an_image_cloudflare_already_holds_counts_as_copied(): void
    {
        Http::fake([self::API.'images/v1' => Http::response([
            'success' => false, 'errors' => [['code' => 5409, 'message' => 'Resource already exists']],
        ], 409)]);
        $this->seedCloudinaryContent();

        $this->artisan('media:move-to-cloudflare', ['--execute' => true])->assertSuccessful();

        $this->assertStringStartsWith('https://imagedelivery.net/', Fabric::first()->image);
    }

    public function test_a_failed_copy_leaves_that_address_alone_and_says_so(): void
    {
        Http::fake([self::API.'images/v1' => Http::response([
            'success' => false, 'errors' => [['code' => 5400, 'message' => 'Bad image']],
        ], 400)]);
        $this->seedCloudinaryContent();

        $this->artisan('media:move-to-cloudflare', ['--execute' => true])
            ->expectsOutputToContain('Bad image')
            ->assertFailed();

        /* Still on Cloudinary rather than pointing at an image that does not exist */
        $this->assertStringContainsString('res.cloudinary.com', Fabric::first()->image);
    }

    public function test_a_second_run_skips_what_the_first_one_copied(): void
    {
        Http::fake([self::API.'images/v1' => Http::response($this->ok(['id' => 'x']))]);
        $this->seedCloudinaryContent();

        $this->artisan('media:move-to-cloudflare', ['--execute' => true])->assertSuccessful();
        $sent = count(Http::recorded());

        $this->artisan('media:move-to-cloudflare', ['--execute' => true])
            ->expectsOutputToContain('0 distinct addresses')
            ->assertSuccessful();

        $this->assertCount($sent, Http::recorded(), 'nothing should be re-sent');
    }
}
