<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SuitConfiguratorController;
use App\Models\Fabric;
use App\Models\Sleeve;
use App\Models\SleeveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultShoulderTest extends TestCase
{
    use RefreshDatabase;

    private array $types = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Default Shoulder' => 'DS', 'English Shoulder' => 'ES', 'Italian Shoulder' => 'IS'] as $name => $code) {
            $this->types[$code] = SleeveType::create(['name' => $name, 'code' => $code, 'diagram' => 'https://cdn.test/d.png']);
        }

        /* Two fabrics, each with its own sleeve of every shoulder style */
        foreach (['Navy Twill', 'Grey Flannel'] as $i => $cloth) {
            $fabric = Fabric::create(['name' => $cloth, 'price' => 200, 'image' => 'https://cdn.test/f.png', 'is_default' => $i === 0, 'status' => true]);

            foreach ($this->types as $type) {
                Sleeve::create(['fabric_id' => $fabric->id, 'sleeve_type_id' => $type->id, 'image' => 'https://cdn.test/s.png', 'is_default' => false, 'status' => true]);
            }
        }
    }

    /** The shoulder each fabric starts on, as the designer reads it. */
    private function startingShoulders(): array
    {
        SuitConfiguratorController::forgetCache();

        return collect($this->getJson('/api/configurator')->assertOk()->json('data'))
            ->map(fn ($fabric) => collect($fabric['sleeves'])->firstWhere('is_default', true)['type']['code'] ?? null)
            ->all();
    }

    public function test_the_shoulder_marked_default_is_where_every_fabric_starts(): void
    {
        $this->types['IS']->update(['is_default' => true]);

        $this->assertSame(['IS', 'IS'], $this->startingShoulders());
    }

    public function test_only_one_shoulder_can_be_the_default(): void
    {
        $this->types['ES']->update(['is_default' => true]);
        $this->types['IS']->update(['is_default' => true]);

        $this->assertSame(['IS'], SleeveType::where('is_default', true)->pluck('code')->all());
        $this->assertSame(['IS', 'IS'], $this->startingShoulders());
    }

    public function test_changing_the_default_reaches_the_designer_straight_away(): void
    {
        $this->types['ES']->update(['is_default' => true]);
        $this->assertSame(['ES', 'ES'], $this->startingShoulders());

        $this->types['DS']->update(['is_default' => true]);
        $this->assertSame(['DS', 'DS'], $this->startingShoulders());
    }

    public function test_with_no_default_shoulder_each_fabric_keeps_its_own(): void
    {
        /* The old per-fabric flag still decides when no shoulder style is marked */
        Sleeve::where('sleeve_type_id', $this->types['ES']->id)->where('fabric_id', Fabric::first()->id)->update(['is_default' => true]);

        $this->assertSame(['ES', null], $this->startingShoulders());
    }
}
