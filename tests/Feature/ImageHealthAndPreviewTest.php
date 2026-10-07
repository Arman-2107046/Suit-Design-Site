<?php

namespace Tests\Feature;

use App\Filament\Pages\DesignPreview;
use App\Filament\Pages\ImageHealth;
use App\Filament\Resources\Lapels\Pages\EditLapel;
use App\Models\Admin;
use App\Models\Body;
use App\Models\BodyButton;
use App\Models\BodyType;
use App\Models\ButtonImage;
use App\Models\ChestPocket;
use App\Models\ChestPocketType;
use App\Models\CustomLiningFabric;
use App\Models\DefaultLining;
use App\Models\Fabric;
use App\Models\ImageIssue;
use App\Models\Lapel;
use App\Models\LapelCategory;
use App\Models\LapelSubCategory;
use App\Models\LiningType;
use App\Models\Sleeve;
use App\Models\SleeveType;
use App\Services\Cloudflare\CloudflareImages;
use App\Services\ImageHealth\ImageHealthCheck;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ImageHealthAndPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const CF = 'https://imagedelivery.net/HASH';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
        $this->app->instance(CloudflareImages::class, new CloudflareImages('acc', 'tok', 'HASH'));
    }

    /** Cloudflare says these ids exist; everything else on the account is gone. */
    private function cloudflareHas(array $ids): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true, 'result' => [
                'images' => array_map(fn ($id) => ['id' => $id], $ids),
                'continuation_token' => null,
            ]]),
            'res.cloudinary.com/*' => Http::response('', 401),
            'cdn.elsewhere.test/*' => Http::response('img', 200),
        ]);
    }

    /** One of everything the designer stacks, on one fabric. */
    private function suit(): array
    {
        $fabric = Fabric::create(['name' => 'Navy Twill', 'price' => 200, 'image' => self::CF.'/fabric-navy/public', 'is_default' => true, 'status' => true]);
        $type = BodyType::create(['name' => 'Single-breasted 1', 'code' => 'SB1', 'diagram' => self::CF.'/type-sb1/public']);
        $body = Body::create(['fabric_id' => $fabric->id, 'body_type_id' => $type->id, 'image' => self::CF.'/body-navy-sb1/public', 'layer_index' => 100, 'is_default' => true, 'status' => true]);
        $style = LapelCategory::create(['name' => 'Peak', 'diagram' => self::CF.'/peak/public', 'status' => true, 'is_default' => true]);
        $width = LapelSubCategory::create(['name' => 'Standard', 'diagram' => self::CF.'/standard/public', 'status' => true, 'is_default' => true]);
        $lapel = Lapel::create(['fabric_id' => $fabric->id, 'body_id' => $body->id, 'lapel_category_id' => $style->id, 'lapel_subcategory_id' => $width->id, 'image' => self::CF.'/lapel-navy-peak/public', 'layer_index' => 150, 'is_default' => true, 'status' => true]);
        $sleeveType = SleeveType::create(['name' => 'Natural', 'code' => 'NAT', 'diagram' => self::CF.'/natural/public', 'is_default' => true]);
        $sleeve = Sleeve::create(['fabric_id' => $fabric->id, 'sleeve_type_id' => $sleeveType->id, 'image' => self::CF.'/sleeve-navy/public', 'layer_index' => 150, 'is_default' => true, 'status' => true]);
        $pocketType = ChestPocketType::create(['name' => 'Welt', 'code' => 'W', 'diagram' => self::CF.'/welt/public']);
        $pocket = ChestPocket::create(['fabric_id' => $fabric->id, 'chest_pocket_type_id' => $pocketType->id, 'image' => self::CF.'/chest-navy/public', 'layer_index' => 100, 'is_default' => true, 'status' => true]);
        $buttonStyle = ButtonImage::create(['name' => 'Horn', 'diagram' => self::CF.'/horn/public']);
        $button = BodyButton::create(['body_type_id' => $type->id, 'button_image_id' => $buttonStyle->id, 'image' => self::CF.'/buttons-sb1-horn/public', 'layer_index' => 120, 'is_default' => true, 'status' => true]);
        $liningType = LiningType::create(['name' => 'Full', 'diagram' => self::CF.'/full/public']);
        $lining = DefaultLining::create(['fabric_id' => $fabric->id, 'body_type_id' => $type->id, 'lining_type_id' => $liningType->id, 'image' => self::CF.'/lining-navy-sb1/public', 'layer_index' => 5, 'status' => true]);

        return compact('fabric', 'type', 'body', 'lapel', 'sleeve', 'pocket', 'button', 'lining');
    }

    /* ── Image health ─────────────────────────────────────────────── */

    public function test_it_finds_pictures_missing_from_cloudflare_refused_by_cloudinary_and_never_uploaded(): void
    {
        $suit = $this->suit();
        CustomLiningFabric::create(['name' => 'Paisley', 'image' => 'https://res.cloudinary.com/acct/image/upload/v1/paisley.png', 'status' => true]);
        CustomLiningFabric::create(['name' => 'Stripe', 'image' => 'https://cdn.elsewhere.test/stripe.png', 'status' => true]);
        $suit['sleeve']->forceFill(['image' => ''])->saveQuietly();

        /* Everything is on Cloudflare except the lapel picture */
        $this->cloudflareHas(['fabric-navy', 'type-sb1', 'body-navy-sb1', 'peak', 'standard', 'natural', 'welt', 'chest-navy', 'horn', 'buttons-sb1-horn', 'full', 'lining-navy-sb1']);

        $run = app(ImageHealthCheck::class)->run();

        $this->assertNull($run->error);
        $this->assertTrue($run->cloudflare_checked);
        $this->assertSame(3, $run->broken_count);

        $issues = ImageIssue::query()->get()->keyBy('reason');
        $this->assertSame('Navy Twill · Single-breasted 1 · Peak · Standard', $issues['not_on_cloudflare']->label);
        $this->assertSame('Lapels', $issues['not_on_cloudflare']->source);
        $this->assertStringEndsWith("/lapels/{$suit['lapel']->id}/edit", $issues['not_on_cloudflare']->edit_url);
        $this->assertSame('Paisley', $issues['refused']->label);
        $this->assertSame(401, $issues['refused']->http_status);
        $this->assertSame('Navy Twill · Natural', $issues['missing']->label);

        /* Cloudflare pictures were checked against the list, not fetched one by one */
        Http::assertSentCount(3);   // the list, Cloudinary, the other host
    }

    public function test_each_run_replaces_the_list_and_the_bell_only_hears_about_new_breakage(): void
    {
        $admin = Admin::factory()->create();
        $suit = $this->suit();
        $this->cloudflareHas(['fabric-navy', 'type-sb1', 'peak', 'standard', 'natural', 'welt', 'chest-navy', 'horn', 'full', 'lining-navy-sb1', 'sleeve-navy', 'lapel-navy-peak']);

        $this->artisan('images:check')->assertSuccessful();
        $this->assertSame(2, ImageIssue::count());   // body and buttons
        $this->assertSame(['2 pictures no longer load'], $admin->fresh()->notifications->pluck('data.title')->all());

        $this->artisan('images:check')->assertSuccessful();
        $this->assertSame(2, ImageIssue::count(), 'replaced, not added to');
        $this->assertSame(1, $admin->fresh()->notifications()->count(), 'nothing new, so no second alert');
    }

    public function test_the_page_lists_problems_with_a_way_to_fix_each(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->suit();
        $this->cloudflareHas(['fabric-navy']);

        Livewire::test(ImageHealth::class)
            ->assertSee('Not checked yet')
            ->callAction('check')
            ->assertSee('Not on Cloudflare')
            ->assertSee('Upload again')
            ->assertSee('Navy Twill · Single-breasted 1');

        $this->assertSame((string) ImageIssue::count(), ImageHealth::getNavigationBadge());
    }

    /* ── Design preview ───────────────────────────────────────────── */

    public function test_the_preview_stacks_layers_exactly_as_the_site_does(): void
    {
        $suit = $this->suit();
        $this->actingAs(Admin::factory()->create(), 'admin');

        $layers = Livewire::test(DesignPreview::class)->instance()->layers();

        /* Sorted by depth; equal depths keep the site's order: body before chest pocket at 100,
           shoulder before lapel at 150 */
        $this->assertSame(['defaultLining', 'body', 'chestPocket', 'button', 'sleeve', 'lapel'], array_column($layers, 'type'));
        $this->assertSame([5, 100, 100, 120, 150, 150], array_column($layers, 'z'));
        $this->assertStringContainsString('lapel-navy-peak/w=1200', $layers[5]['image']);
    }

    public function test_hidden_items_can_still_be_previewed_and_are_marked(): void
    {
        $suit = $this->suit();
        $suit['lapel']->update(['status' => false]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        $lapel = collect(Livewire::test(DesignPreview::class)->instance()->layers())->firstWhere('type', 'lapel');

        $this->assertTrue($lapel['hidden']);
    }

    public function test_a_layer_edit_page_opens_the_preview_on_that_layer(): void
    {
        $suit = $this->suit();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(EditLapel::class, ['record' => $suit['lapel']->getKey()])
            ->assertActionExists('previewInSuit')
            ->assertActionHasUrl('previewInSuit', DesignPreview::urlFor($suit['lapel']));

        $url = DesignPreview::urlFor($suit['button']);
        $this->assertStringContainsString("body={$suit['body']->id}", $url, 'buttons are shown on a body of their style');
        $this->assertStringContainsString('focus=button', $url);

        $this->get(DesignPreview::urlFor($suit['lapel']))->assertOk()->assertSee('Peak · Standard');
    }
}
