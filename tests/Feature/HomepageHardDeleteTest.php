<?php

namespace Tests\Feature;

use App\Filament\Pages\Homepage;
use App\Models\HomepageSetting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class HomepageHardDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudflare.account_id' => 'acc',
            'services.cloudflare.api_token' => 'token',
            'services.cloudflare.images_hash' => 'HASH',
        ]);

        /* The files are gone, as they are on the disabled Cloudinary account */
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false], 404)]);

        $this->actingAs(User::factory()->create());

        HomepageSetting::current()->update([
            'hero_image' => 'homepage/01DEADHERO.png',
            'hero_image_url' => 'https://res.cloudinary.com/disabled/image/upload/v1/homepage/01DEADHERO.png',
            'payment_logos' => [
                ['path' => 'homepage/logos/visa.jpg', 'url' => 'https://res.cloudinary.com/disabled/image/upload/v1/homepage/logos/visa.jpg'],
                ['path' => 'homepage/logos/amex.jpg', 'url' => 'https://res.cloudinary.com/disabled/image/upload/v1/homepage/logos/amex.jpg'],
            ],
        ]);
    }

    public function test_an_image_that_will_not_load_can_still_be_deleted(): void
    {
        Livewire::test(Homepage::class)
            ->callAction(TestAction::make('delete_hero_image')->schemaComponent('hero_image', schema: 'form'))
            ->assertNotified('Image deleted');

        $settings = HomepageSetting::current();
        $this->assertNull($settings->hero_image);
        $this->assertNull($settings->hero_image_url);
    }

    public function test_a_logo_list_that_will_not_load_can_be_cleared(): void
    {
        Livewire::test(Homepage::class)
            ->callAction(TestAction::make('delete_payment_logos')->schemaComponent('payment_logos', schema: 'form'))
            ->assertNotified('Logos deleted');

        $this->assertSame([], HomepageSetting::current()->payment_logos);
    }

    public function test_deleting_one_slot_leaves_the_others_alone(): void
    {
        Livewire::test(Homepage::class)
            ->callAction(TestAction::make('delete_hero_image')->schemaComponent('hero_image', schema: 'form'));

        $this->assertCount(2, HomepageSetting::current()->payment_logos);
    }

    public function test_the_delete_button_only_shows_where_there_is_something_to_delete(): void
    {
        /* A hidden hint action is not registered at all, so this reads the page itself */
        Livewire::test(Homepage::class)
            ->assertActionVisible(TestAction::make('delete_hero_image')->schemaComponent('hero_image', schema: 'form'))
            ->assertSeeHtml('delete_hero_image')
            ->assertDontSeeHtml('delete_planet_image');
    }
}
