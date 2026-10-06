<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SuitConfiguratorController;
use App\Models\Body;
use App\Models\BodyType;
use App\Models\Fabric;
use App\Models\Lapel;
use App\Models\LapelCategory;
use App\Models\LapelSubCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultLapelTest extends TestCase
{
    use RefreshDatabase;

    private array $style = [];

    private array $width = [];

    private Body $body;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Notch', 'Peak', 'Shawl'] as $name) {
            $this->style[$name] = LapelCategory::create(['name' => $name, 'diagram' => 'https://cdn.test/d.png', 'status' => true, 'is_default' => false]);
        }
        foreach (['Standard', 'Narrow', 'Wide'] as $name) {
            $this->width[$name] = LapelSubCategory::create(['name' => $name, 'diagram' => 'https://cdn.test/w.png', 'status' => true, 'is_default' => false]);
        }

        $fabric = Fabric::create(['name' => 'Navy Twill', 'price' => 200, 'image' => 'https://cdn.test/f.png', 'is_default' => true, 'status' => true]);
        $type = BodyType::create(['name' => 'SB1', 'code' => 'SB1', 'diagram' => 'https://cdn.test/b.png']);
        $this->body = Body::create(['fabric_id' => $fabric->id, 'body_type_id' => $type->id, 'image' => 'https://cdn.test/b.png', 'is_default' => true, 'status' => true]);

        /* Every style in every width, except Shawl, which this body only has in Standard */
        foreach ($this->style as $styleName => $style) {
            foreach ($this->width as $widthName => $width) {
                if ($styleName === 'Shawl' && $widthName !== 'Standard') {
                    continue;
                }
                Lapel::create([
                    'fabric_id' => $fabric->id, 'body_id' => $this->body->id,
                    'lapel_category_id' => $style->id, 'lapel_subcategory_id' => $width->id,
                    'image' => 'https://cdn.test/l.png', 'is_default' => false, 'status' => true,
                ]);
            }
        }
    }

    /** "Style / Width" of the lapel the designer starts on. */
    private function startingLapel(): ?string
    {
        SuitConfiguratorController::forgetCache();

        $lapel = collect($this->getJson('/api/configurator')->assertOk()->json('data.0.bodies.0.lapels'))
            ->firstWhere('is_default', true);

        return $lapel ? $lapel['category']['name'].' / '.$lapel['subcategory']['name'] : null;
    }

    public function test_the_default_style_and_width_together_pick_the_starting_lapel(): void
    {
        $this->style['Peak']->update(['is_default' => true]);
        $this->width['Wide']->update(['is_default' => true]);

        $this->assertSame('Peak / Wide', $this->startingLapel());
    }

    public function test_only_one_style_and_one_width_can_be_default(): void
    {
        $this->style['Notch']->update(['is_default' => true]);
        $this->style['Peak']->update(['is_default' => true]);
        $this->width['Narrow']->update(['is_default' => true]);
        $this->width['Wide']->update(['is_default' => true]);

        $this->assertSame(['Peak'], LapelCategory::where('is_default', true)->pluck('name')->all());
        $this->assertSame(['Wide'], LapelSubCategory::where('is_default', true)->pluck('name')->all());
    }

    public function test_the_style_wins_when_this_body_lacks_that_width_in_it(): void
    {
        /* Shawl only comes in Standard here, so Shawl / Narrow cannot be had */
        $this->style['Shawl']->update(['is_default' => true]);
        $this->width['Narrow']->update(['is_default' => true]);

        $this->assertSame('Shawl / Standard', $this->startingLapel());
    }

    public function test_a_default_style_alone_starts_on_its_first_width(): void
    {
        $this->style['Peak']->update(['is_default' => true]);

        $this->assertSame('Peak / Standard', $this->startingLapel());
    }

    public function test_a_default_width_alone_starts_on_the_first_style_in_it(): void
    {
        $this->width['Wide']->update(['is_default' => true]);

        $this->assertSame('Notch / Wide', $this->startingLapel());
    }

    public function test_the_admins_choice_overrides_a_single_lapels_own_flag(): void
    {
        Lapel::where('lapel_category_id', $this->style['Notch']->id)->first()->update(['is_default' => true]);
        $this->style['Peak']->update(['is_default' => true]);

        $this->assertSame('Peak / Standard', $this->startingLapel());
    }

    public function test_with_nothing_marked_a_lapels_own_flag_still_decides(): void
    {
        Lapel::where('lapel_category_id', $this->style['Shawl']->id)->first()->update(['is_default' => true]);

        $this->assertSame('Shawl / Standard', $this->startingLapel());
    }
}
