<?php

namespace Tests\Feature;

use App\Models\Body;
use App\Models\BodyType;
use App\Models\Fabric;
use App\Models\Lapel;
use App\Models\LapelCategory;
use App\Models\LapelSubCategory;
use App\Models\Sleeve;
use App\Models\SleeveType;
use App\Http\Controllers\Api\SuitConfiguratorController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguratorOrderingTest extends TestCase
{
    use RefreshDatabase;

    private Fabric $fabric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fabric = Fabric::create([
            'name' => 'Navy Twill', 'price' => 200, 'image' => 'https://cdn.test/f.png',
            'is_default' => true, 'status' => true,
        ]);
    }

    /**
     * Drag the types into $order, then read back what the designer would show.
     */
    private function orderOf(string $table, array $order, string $path): array
    {
        foreach ($order as $position => $name) {
            \DB::table($table)->where('name', $name)->update(['sort_order' => $position + 1]);
        }

        SuitConfiguratorController::forgetCache();

        return data_get($this->getJson('/api/configurator')->assertOk()->json(), $path);
    }

    public function test_body_style_follows_the_order_body_types_were_dragged_into(): void
    {
        foreach (['Double-breasted 2', 'Single-breasted 1', 'Single-breasted 2'] as $i => $name) {
            $type = BodyType::create(['name' => $name, 'code' => "T{$i}", 'diagram' => 'https://cdn.test/d.png']);
            Body::create([
                'fabric_id' => $this->fabric->id, 'body_type_id' => $type->id,
                'image' => 'https://cdn.test/b.png', 'is_default' => $i === 0, 'status' => true,
            ]);
        }

        $wanted = ['Single-breasted 1', 'Single-breasted 2', 'Double-breasted 2'];

        $this->assertSame($wanted, $this->orderOf('body_types', $wanted, 'data.0.bodies.*.body_type.name'));

        /* And it really follows the drag rather than landing there by luck */
        $reversed = array_reverse($wanted);

        $this->assertSame($reversed, $this->orderOf('body_types', $reversed, 'data.0.bodies.*.body_type.name'));
    }

    public function test_lapel_types_follow_the_order_lapel_categories_were_dragged_into(): void
    {
        $type = BodyType::create(['name' => 'SB1', 'code' => 'SB1', 'diagram' => 'https://cdn.test/d.png']);
        $body = Body::create([
            'fabric_id' => $this->fabric->id, 'body_type_id' => $type->id,
            'image' => 'https://cdn.test/b.png', 'is_default' => true, 'status' => true,
        ]);

        $width = LapelSubCategory::create(['name' => 'Standard', 'diagram' => 'https://cdn.test/w.png', 'status' => true, 'is_default' => true]);

        foreach (['Shawl', 'Notch', 'Peak'] as $i => $name) {
            $category = LapelCategory::create(['name' => $name, 'diagram' => 'https://cdn.test/l.png', 'status' => true, 'is_default' => $i === 0]);
            Lapel::create([
                'fabric_id' => $this->fabric->id, 'body_id' => $body->id,
                'lapel_category_id' => $category->id, 'lapel_subcategory_id' => $width->id,
                'image' => 'https://cdn.test/lp.png', 'is_default' => $i === 0, 'status' => true,
            ]);
        }

        $wanted = ['Peak', 'Shawl', 'Notch'];

        $this->assertSame(
            $wanted,
            $this->orderOf('lapel_categories', $wanted, 'data.0.bodies.0.lapels.*.category.name')
        );
    }

    public function test_sleeves_follow_the_order_sleeve_types_were_dragged_into(): void
    {
        foreach (['Buttonholes', 'Plain', 'Working'] as $i => $name) {
            $type = SleeveType::create(['name' => $name, 'code' => "S{$i}", 'diagram' => 'https://cdn.test/d.png']);
            Sleeve::create([
                'fabric_id' => $this->fabric->id, 'sleeve_type_id' => $type->id,
                'image' => 'https://cdn.test/s.png',
                'is_default' => $i === 0, 'status' => true,
            ]);
        }

        $wanted = ['Plain', 'Working', 'Buttonholes'];

        $this->assertSame($wanted, $this->orderOf('sleeve_types', $wanted, 'data.0.sleeves.*.type.name'));
    }
}
