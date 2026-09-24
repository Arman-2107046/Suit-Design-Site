<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SuitConfiguratorController;
use App\Models\DesignerSetting;
use App\Models\Fabric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignerLayoutSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Fabric::create([
            'name' => 'Navy Twill', 'price' => 200, 'image' => 'https://cdn.test/f.png',
            'is_default' => true, 'status' => true,
        ]);
    }

    private function layout(): array
    {
        SuitConfiguratorController::forgetCache();

        return $this->getJson('/api/configurator')->assertOk()->json('layout');
    }

    public function test_an_untouched_shop_gets_the_layout_the_designer_shipped_with(): void
    {
        $this->assertSame([
            'fabric_columns' => 3,
            'style_layout' => 'grid',
            'style_columns' => 3,
            'lining_columns' => 2,
        ], $this->layout());
    }

    public function test_what_the_admin_chooses_reaches_the_designer(): void
    {
        DesignerSetting::current()->update([
            'fabric_columns' => 4,
            'style_layout' => 'slider',
            'lining_columns' => 3,
        ]);

        $layout = $this->layout();

        $this->assertSame(4, $layout['fabric_columns']);
        $this->assertSame('slider', $layout['style_layout']);
        $this->assertSame(3, $layout['lining_columns']);
    }

    public function test_saving_the_layout_drops_the_cached_payload(): void
    {
        $this->assertSame(3, $this->layout()['fabric_columns']);

        /* No forgetCache() here — saving the setting has to do it */
        DesignerSetting::current()->update(['fabric_columns' => 5]);

        $this->assertSame(5, $this->getJson('/api/configurator')->assertOk()->json('layout.fabric_columns'));
    }

    public function test_a_column_count_the_designer_cannot_draw_falls_back(): void
    {
        /* Straight past the model so nothing sanitises on the way in */
        \DB::table('designer_settings')->insert([
            'fabric_columns' => 99,
            'style_layout' => 'carousel',
            'style_columns' => 0,
            'lining_columns' => 7,
        ]);

        $this->assertSame([
            'fabric_columns' => 3,
            'style_layout' => 'grid',
            'style_columns' => 3,
            'lining_columns' => 2,
        ], $this->layout());
    }
}
