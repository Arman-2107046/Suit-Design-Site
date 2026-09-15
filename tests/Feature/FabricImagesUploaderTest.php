<?php

namespace Tests\Feature;

use App\Models\Fabric;
use App\Services\BulkUpload\FabricImagesUploader;
use App\Services\BulkUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FabricImagesUploaderTest extends TestCase
{
    use RefreshDatabase;

    private function fabric(): Fabric
    {
        return Fabric::create(['name' => 'Blue Stripe', 'price' => 120, 'image' => 'https://example.test/s.png', 'is_default' => true, 'status' => true]);
    }

    public function test_preview_and_real_life_pictures_are_attached_in_order(): void
    {
        $fabric = $this->fabric();
        $uploader = app(FabricImagesUploader::class);

        $this->assertTrue($uploader->handle(['name' => 'FPI_Blue Stripe_2.png', 'url' => 'https://cdn/2.png'])['success']);
        $this->assertTrue($uploader->handle(['name' => 'FPI_Blue Stripe_1.png', 'url' => 'https://cdn/1.png'])['success']);
        $this->assertTrue($uploader->handle(['name' => 'FPI_Blue Stripe.png', 'url' => 'https://cdn/3.png'])['success']);
        $this->assertTrue($uploader->handle(['name' => 'FRL_Blue Stripe_1.png', 'url' => 'https://cdn/real.png'])['success']);

        $this->assertSame(['https://cdn/1.png', 'https://cdn/2.png', 'https://cdn/3.png'], $fabric->images()->where('kind', 'preview')->pluck('url')->all());
        $this->assertSame(['https://cdn/real.png'], $fabric->images()->where('kind', 'real_life')->pluck('url')->all());
    }

    public function test_a_slot_can_be_replaced_and_the_cap_is_enforced(): void
    {
        $fabric = $this->fabric();
        $uploader = app(FabricImagesUploader::class);

        foreach (range(1, 10) as $i) {
            $uploader->handle(['name' => "FPI_Blue Stripe_{$i}.png", 'url' => "https://cdn/{$i}.png"]);
        }

        $this->assertFalse($uploader->handle(['name' => 'FPI_Blue Stripe.png', 'url' => 'https://cdn/11.png'])['success']);
        $this->assertTrue($uploader->handle(['name' => 'FPI_Blue Stripe_4.png', 'url' => 'https://cdn/4b.png'])['success']);

        $this->assertSame(10, $fabric->images()->count());
        $this->assertSame('https://cdn/4b.png', $fabric->images()->where('sort_order', 4)->value('url'));
    }

    public function test_unknown_fabric_and_bad_names_fail_clearly(): void
    {
        $this->fabric();
        $service = app(BulkUploadService::class);

        $result = $service->process([
            ['name' => 'FPI_Nope_1.png', 'url' => 'https://cdn/x.png'],
            ['name' => 'FPI.png', 'url' => 'https://cdn/y.png'],
            ['name' => 'FRL_Blue Stripe_1.png', 'url' => 'https://cdn/ok.png'],
        ]);

        $this->assertFalse($result['success']);
        $this->assertCount(2, $result['failed']);
        $this->assertStringContainsString('not found', $result['failed'][0]['message']);
    }

    public function test_pictures_and_details_reach_the_configurator_payload(): void
    {
        $fabric = $this->fabric();
        $fabric->update(['is_new' => true]);
        $fabric->infos()->create(['title' => 'Giuliano', 'column_1' => 'Brand: Loro Piana, Occasion: Business, Casual']);
        app(FabricImagesUploader::class)->handle(['name' => 'FPI_Blue Stripe_1.png', 'url' => 'https://cdn/1.png']);

        $this->getJson('/api/configurator')
            ->assertOk()
            ->assertJsonPath('data.0.is_new', true)
            ->assertJsonPath('data.0.info.title', 'Giuliano')
            ->assertJsonPath('data.0.info.columns.0.1.value', 'Business, Casual')
            ->assertJsonPath('data.0.preview_images', ['https://cdn/1.png']);
    }
}
