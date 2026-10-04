<?php

namespace Tests\Feature;

use App\Models\Fabric;
use App\Models\HomepageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Unit\ColorProfileTest;

class FixImageColorProfilesTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.cloudflare.com/client/v4/accounts/acc123/';

    private const DELIVERY = 'https://imagedelivery.net/HASH/';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudflare.account_id' => 'acc123',
            'services.cloudflare.api_token' => 'test-token',
            'services.cloudflare.images_hash' => 'HASH',
        ]);

        @unlink(storage_path('app/color-profile-fix.json'));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/color-profile-fix.json'));

        parent::tearDown();
    }

    /** Cloudflare, holding one image already in sRGB and the rest untagged Adobe RGB. */
    private function fakeCloudflare(): void
    {
        $adobe = ColorProfileTest::png(ColorProfileTest::xmp(65535));
        $srgb = ColorProfileTest::png(ColorProfileTest::xmp(1));

        Http::fake(function (Request $r) use ($adobe, $srgb) {
            if (str_ends_with($r->url(), '/blob')) {
                return Http::response(str_contains($r->url(), 'already-srgb') ? $srgb : $adobe);
            }

            if ($r->method() === 'POST' && $r->url() === self::API.'images/v1') {
                return Http::response(['success' => true, 'errors' => [], 'result' => []]);
            }

            return Http::response(['success' => false], 404);
        });
    }

    private function seedImages(): void
    {
        foreach (['faded-body' => self::DELIVERY.'6c919fe2-uuid/public', 'srgb-body' => self::DELIVERY.'already-srgb/public'] as $name => $image) {
            Fabric::create(['name' => $name, 'price' => 200, 'is_default' => false, 'status' => true, 'image' => $image]);
        }

        HomepageSetting::query()->insert([
            'hero_image' => 'homepage/01HERO.png',
            'hero_image_url' => self::DELIVERY.'homepage/01HERO.png/public',
            'payment_logos' => json_encode([['path' => 'homepage/logos/visa.jpg', 'url' => self::DELIVERY.'homepage/logos/visa.jpg/public']]),
        ]);
    }

    public function test_a_dry_run_reports_without_uploading_or_rewriting(): void
    {
        $this->fakeCloudflare();
        $this->seedImages();

        $this->artisan('media:fix-color-profiles')
            ->expectsOutputToContain('4 images in use, 4 still to check.')
            ->expectsOutputToContain('3 of the images checked are missing their Adobe RGB profile.')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
        $this->assertSame(self::DELIVERY.'6c919fe2-uuid/public', Fabric::where('name', 'faded-body')->value('image'));
    }

    public function test_executing_uploads_a_tagged_copy_and_points_everything_at_it(): void
    {
        $this->fakeCloudflare();
        $this->seedImages();

        $this->artisan('media:fix-color-profiles', ['--execute' => true])
            ->expectsOutputToContain('3 images fixed in all')
            ->assertSuccessful();

        /* The copy went up under its new id, carrying the profile. */
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_contains($r->body(), 'srgb/6c919fe2-uuid')
            && str_contains($r->body(), 'iCCP'));

        $this->assertSame(self::DELIVERY.'srgb/6c919fe2-uuid/public', Fabric::where('name', 'faded-body')->value('image'));
        $this->assertSame(self::DELIVERY.'already-srgb/public', Fabric::where('name', 'srgb-body')->value('image'));

        /* A form's saved path and its address move together, so they still agree. */
        $settings = HomepageSetting::first();
        $this->assertSame('srgb/homepage/01HERO.png', $settings->hero_image);
        $this->assertSame(Storage::disk('cloudflare')->url($settings->hero_image), $settings->hero_image_url);

        /* Paths and addresses inside JSON, slashes escaped, too. */
        $this->assertSame('srgb/homepage/logos/visa.jpg', $settings->payment_logos[0]['path']);
        $this->assertSame(self::DELIVERY.'srgb/homepage/logos/visa.jpg/public', $settings->payment_logos[0]['url']);

        /* The originals are kept unless asked: another copy of the site may still use them. */
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE');
    }

    public function test_a_second_run_finds_nothing_left_to_do(): void
    {
        $this->fakeCloudflare();
        $this->seedImages();
        $this->artisan('media:fix-color-profiles', ['--execute' => true])->assertSuccessful();

        Http::fake();
        $this->artisan('media:fix-color-profiles', ['--execute' => true])
            ->expectsOutputToContain('0 still to check')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(self::DELIVERY.'srgb/6c919fe2-uuid/public', Fabric::where('name', 'faded-body')->value('image'));
    }

    public function test_the_originals_go_only_when_asked(): void
    {
        $this->fakeCloudflare();
        $this->seedImages();

        $this->artisan('media:fix-color-profiles', ['--execute' => true, '--delete-old' => true])
            ->expectsOutputToContain('Deleted 3 originals')
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::API.'images/v1/6c919fe2-uuid');
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'already-srgb'));
    }

    public function test_a_form_upload_through_the_disk_gets_the_profile_too(): void
    {
        $this->fakeCloudflare();

        Storage::disk('cloudflare')->put('homepage/hero.png', ColorProfileTest::png(ColorProfileTest::xmp(65535)));

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->body(), 'iCCP'));
    }
}
