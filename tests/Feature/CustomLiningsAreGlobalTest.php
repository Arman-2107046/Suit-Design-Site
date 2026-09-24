<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SuitConfiguratorController;
use App\Models\CustomLining;
use App\Models\CustomLiningFabric;
use App\Models\Fabric;
use App\Models\LiningType;
use App\Services\BulkUpload\CustomLiningsUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomLiningsAreGlobalTest extends TestCase
{
    use RefreshDatabase;

    private function fabric(string $name): Fabric
    {
        return Fabric::create([
            'name' => $name, 'price' => 200, 'image' => 'https://cdn.test/f.png',
            'is_default' => $name === 'Navy Twill', 'status' => true,
        ]);
    }

    private function lining(string $type, string $cloth): void
    {
        LiningType::firstOrCreate(['name' => $type], ['diagram' => 'https://cdn.test/d.png']);
        CustomLiningFabric::firstOrCreate(['name' => $cloth], ['image' => 'https://cdn.test/c.png']);
    }

    private function upload(string $filename): array
    {
        return app(CustomLiningsUploader::class)->handle([
            'name' => $filename,
            'url' => 'https://cdn.test/'.md5($filename).'.png',
        ]);
    }

    public function test_a_lining_is_named_without_a_fabric_and_offered_on_all_of_them(): void
    {
        $this->fabric('Navy Twill');
        $this->fabric('Charcoal Herringbone');
        $this->lining('Full Lining', 'Blue Silk');

        $this->assertTrue($this->upload('CL_Full Lining_Blue Silk.png')['success']);

        SuitConfiguratorController::forgetCache();

        $payload = $this->getJson('/api/configurator')->assertOk()->json();

        $this->assertCount(2, $payload['data']);

        foreach ($payload['data'] as $fabric) {
            $this->assertCount(1, $fabric['custom_linings'], "{$fabric['name']} should offer the lining");
            $this->assertSame('Blue Silk', $fabric['custom_linings'][0]['fabric']['name']);
            $this->assertSame('Full Lining', $fabric['custom_linings'][0]['type']['name']);
        }

        /* The same row on every fabric, so a chosen lining survives switching fabric */
        $this->assertSame(
            $payload['data'][0]['custom_linings'][0]['id'],
            $payload['data'][1]['custom_linings'][0]['id']
        );
    }

    public function test_uploading_the_same_lining_twice_replaces_it_rather_than_duplicating(): void
    {
        $this->fabric('Navy Twill');
        $this->lining('Full Lining', 'Blue Silk');

        $this->upload('CL_Full Lining_Blue Silk.png');

        app(CustomLiningsUploader::class)->handle([
            'name' => 'CL_Full Lining_Blue Silk.png',
            'url' => 'https://cdn.test/replacement.png',
        ]);

        $this->assertSame(1, CustomLining::count());
        $this->assertSame('https://cdn.test/replacement.png', CustomLining::first()->image);
    }

    public function test_the_old_four_part_name_is_rejected_with_the_new_convention(): void
    {
        $this->fabric('Black Wool');
        $this->lining('Full Lining', 'Blue Silk');

        $result = $this->upload('CL_Full Lining_Blue Silk_Black Wool.png');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('CL_LiningType_LiningFabric', $result['message']);
        $this->assertSame(0, CustomLining::count());
    }

    public function test_an_unknown_lining_type_or_cloth_still_fails_clearly(): void
    {
        $this->fabric('Navy Twill');
        $this->lining('Full Lining', 'Blue Silk');

        $this->assertStringContainsString('lining type not found', $this->upload('CL_Nope_Blue Silk.png')['message']);
        $this->assertStringContainsString('custom lining fabric not found', $this->upload('CL_Full Lining_Nope.png')['message']);
    }
}
