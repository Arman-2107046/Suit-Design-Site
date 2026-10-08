<?php

namespace Tests\Feature;

use App\Filament\Resources\ButtonImages\Pages\ListButtonImages;
use App\Filament\Resources\CustomLiningFabrics\Pages\ListCustomLiningFabrics;
use App\Filament\Resources\Fabrics\Pages\ListFabrics;
use App\Http\Controllers\Api\SuitConfiguratorController;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\Body;
use App\Models\BodyButton;
use App\Models\BodyType;
use App\Models\ButtonImage;
use App\Models\CustomLiningFabric;
use App\Models\Fabric;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogueCardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function fabric(array $overrides = []): Fabric
    {
        return Fabric::create($overrides + ['name' => 'Navy Twill', 'price' => 249, 'image' => 'https://imagedelivery.net/h/navy/public', 'is_default' => true, 'status' => true]);
    }

    public function test_the_catalogue_screens_show_swatch_cards_with_small_pictures(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->fabric();
        CustomLiningFabric::create(['name' => 'Paisley', 'image' => 'https://imagedelivery.net/h/paisley/public', 'status' => true]);
        ButtonImage::create(['name' => 'Horn', 'diagram' => 'https://imagedelivery.net/h/horn/public']);

        foreach ([ListFabrics::class, ListCustomLiningFabrics::class, ListButtonImages::class] as $page) {
            Livewire::test($page)
                ->assertSeeHtml('ct-swatch')
                ->assertSeeHtml('w=640,fit=scale-down,f=auto')   // never the multi-megabyte original
                ->assertSee('Live');
        }
    }

    public function test_the_switch_on_a_card_hides_and_shows_an_item_and_is_logged(): void
    {
        $this->actingAs(Admin::factory()->create(['name' => 'Sara']), 'admin');
        $fabric = $this->fabric();

        Livewire::test(ListFabrics::class)->call('updateTableColumnState', 'status', (string) $fabric->getKey(), false);
        $this->assertFalse($fabric->fresh()->status);

        Livewire::test(ListFabrics::class)->assertSee('Hidden')->assertSeeHtml('ct-swatch-off');

        Livewire::test(ListFabrics::class)->call('updateTableColumnState', 'status', (string) $fabric->getKey(), true);
        $this->assertTrue($fabric->fresh()->status);

        $this->assertSame(2, Activity::where('event', 'updated')->where('admin_name', 'Sara')->count());
    }

    public function test_a_picture_list_switches_between_grid_and_list_and_remembers_it(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->fabric();
        $this->fabric(['name' => 'Grey Flannel', 'is_default' => false]);

        Livewire::test(ListFabrics::class)
            ->assertSeeHtml('ct-swatch')
            ->call('switchImageView', 'list')
            ->assertDontSeeHtml('ct-swatch ')
            ->assertSee('Cloth')
            ->assertSeeHtml('w=160,fit=scale-down,f=auto')
            /* the search still finds by name in either view */
            ->set('tableSearch', 'Flannel')
            ->assertSee('Grey Flannel')
            ->assertDontSee('Navy Twill');

        /* Coming back to the page keeps the rows */
        Livewire::test(ListFabrics::class)->assertSet('imageView', 'list')->assertDontSeeHtml('ct-swatch ');

        Livewire::test(ListFabrics::class)
            ->call('switchImageView', 'grid')
            ->assertSeeHtml('ct-swatch')
            ->set('tableSearch', 'Flannel')
            ->assertSee('Grey Flannel')
            ->assertDontSee('Navy Twill')
            ->call('switchImageView', 'sideways')
            ->assertSet('imageView', 'grid');
    }

    public function test_a_button_style_switched_off_leaves_the_configurator(): void
    {
        $fabric = $this->fabric();
        $type = BodyType::create(['name' => 'Single-breasted 1', 'code' => 'SB1', 'diagram' => 'https://cdn.test/d.png']);
        Body::create(['fabric_id' => $fabric->id, 'body_type_id' => $type->id, 'image' => 'https://cdn.test/b.png', 'is_default' => true, 'status' => true]);

        $horn = ButtonImage::create(['name' => 'Horn', 'diagram' => 'https://cdn.test/horn.png']);
        $metal = ButtonImage::create(['name' => 'Metal', 'diagram' => 'https://cdn.test/metal.png']);
        foreach ([$horn, $metal] as $i => $button) {
            BodyButton::create(['body_type_id' => $type->id, 'button_image_id' => $button->id, 'image' => "https://cdn.test/bb{$i}.png", 'layer_index' => 3, 'is_default' => $i === 0, 'status' => true]);
        }

        $names = fn () => collect(data_get($this->getJson('/api/configurator')->assertOk()->json(), 'data.0.bodies.0.body_type.body_buttons'))
            ->pluck('button_image.name')->sort()->values()->all();

        $this->assertSame(['Horn', 'Metal'], $names());

        $metal->update(['status' => false]);   // the model clears the configurator cache itself
        $this->assertSame(['Horn'], $names());
    }
}
