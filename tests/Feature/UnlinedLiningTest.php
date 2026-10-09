<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SuitConfiguratorController;
use App\Models\Fabric;
use App\Models\LiningType;
use App\Models\UnlinedLining;
use App\Services\BulkUpload\UnlinedLiningsUploader;
use App\Services\BulkUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnlinedLiningTest extends TestCase
{
    use RefreshDatabase;

    private function fabric(string $name): Fabric
    {
        return Fabric::create([
            'name' => $name, 'price' => 200, 'image' => 'https://cdn.test/f.png',
            'is_default' => $name === 'Navy Twill', 'status' => true,
        ]);
    }

    private function upload(string $filename, ?string $url = null): array
    {
        return app(UnlinedLiningsUploader::class)->handle([
            'name' => $filename,
            'url' => $url ?? 'https://cdn.test/'.md5($filename).'.png',
        ], str_starts_with($filename, 'ULP_') ? UnlinedLining::PLATE : UnlinedLining::UNLINED);
    }

    protected function setUp(): void
    {
        parent::setUp();

        LiningType::create(['name' => 'Unlined', 'diagram' => 'https://cdn.test/unlined-tile.png']);
        LiningType::create(['name' => 'Unlined Plate', 'diagram' => 'https://cdn.test/plate-tile.png']);
    }

    public function test_a_file_named_for_a_fabric_becomes_that_fabrics_unlined_picture(): void
    {
        $navy = $this->fabric('Navy Twill');
        $this->fabric('Blue Stripe');

        $this->assertTrue($this->upload('UL_Unlined_Navy Twill.png', 'https://cdn.test/navy-unlined.png')['success']);

        $row = UnlinedLining::sole();
        $this->assertSame($navy->id, $row->fabric_id);
        $this->assertSame('https://cdn.test/navy-unlined.png', $row->image);
        $this->assertSame('Unlined', $row->liningType->name);
        $this->assertTrue($row->status);
    }

    public function test_a_fabric_name_with_underscores_is_read_as_spaces(): void
    {
        $this->fabric('Blue Stripe');

        $this->assertTrue($this->upload('UL_Unlined_Blue_Stripe.png')['success']);
        $this->assertSame(1, UnlinedLining::count());
    }

    public function test_uploading_again_replaces_the_picture_instead_of_adding_another(): void
    {
        $this->fabric('Navy Twill');

        $this->upload('UL_Unlined_Navy Twill.png', 'https://cdn.test/one.png');
        $this->upload('UL_Unlined_Navy Twill.png', 'https://cdn.test/two.png');

        $this->assertSame(1, UnlinedLining::count());
        $this->assertSame('https://cdn.test/two.png', UnlinedLining::sole()->image);
    }

    public function test_a_wrong_name_is_refused_with_the_reason_and_files_nothing(): void
    {
        $this->fabric('Navy Twill');

        $unknownFabric = $this->upload('UL_Unlined_Nowhere Cloth.png');
        $this->assertFalse($unknownFabric['success']);
        $this->assertStringContainsString('Nowhere Cloth fabric not found', $unknownFabric['message']);

        $unknownType = $this->upload('UL_Plain_Navy Twill.png');
        $this->assertFalse($unknownType['success']);
        $this->assertStringContainsString('Plain lining type not found', $unknownType['message']);

        $noFabric = $this->upload('UL_Unlined.png');
        $this->assertFalse($noFabric['success']);
        $this->assertStringContainsString('Expected: UL_Unlined_FabricName', $noFabric['message']);

        $this->assertSame(0, UnlinedLining::count());
    }

    public function test_the_bulk_uploader_routes_ul_files_here_after_their_fabric_and_lining_type(): void
    {
        $this->fabric('Navy Twill');

        $result = app(BulkUploadService::class)->process([
            ['name' => 'UL_Unlined_Navy Twill.png', 'url' => 'https://cdn.test/u.png'],
            ['name' => 'FAB_150_Late Fabric.png', 'url' => 'https://cdn.test/f.png'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, UnlinedLining::count());
        $this->assertGreaterThan(BulkUploadService::PRIORITY['FAB'], BulkUploadService::PRIORITY['UL']);
        $this->assertGreaterThan(BulkUploadService::PRIORITY['LT'], BulkUploadService::PRIORITY['UL']);
    }

    public function test_the_plate_is_kept_beside_the_plain_unlined_picture_of_the_same_fabric(): void
    {
        $navy = $this->fabric('Navy Twill');

        $this->assertTrue($this->upload('UL_Unlined_Navy Twill.png', 'https://cdn.test/plain.png')['success']);
        $this->assertTrue($this->upload('ULP_Unlined Plate_Navy Twill.png', 'https://cdn.test/plate.png')['success']);

        $this->assertSame(2, UnlinedLining::count());
        $this->assertSame('https://cdn.test/plain.png', UnlinedLining::where('kind', 'unlined')->sole()->image);

        $plate = UnlinedLining::where('kind', 'plate')->sole();
        $this->assertSame($navy->id, $plate->fabric_id);
        $this->assertSame('https://cdn.test/plate.png', $plate->image);
        $this->assertSame('Unlined Plate', $plate->liningType->name);

        /* Again replaces the plate only */
        $this->upload('ULP_Unlined Plate_Navy Twill.png', 'https://cdn.test/plate-2.png');
        $this->assertSame(2, UnlinedLining::count());
        $this->assertSame('https://cdn.test/plain.png', UnlinedLining::where('kind', 'unlined')->sole()->image);
    }

    public function test_a_plate_file_with_a_wrong_name_says_what_it_expected(): void
    {
        $this->fabric('Navy Twill');

        $result = $this->upload('ULP_Unlined Plate.png');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Expected: ULP_Unlined Plate_FabricName', $result['message']);
    }

    public function test_the_bulk_uploader_routes_ulp_files_to_the_plate(): void
    {
        $this->fabric('Navy Twill');

        $result = app(BulkUploadService::class)->process([
            ['name' => 'ULP_Unlined Plate_Navy Twill.png', 'url' => 'https://cdn.test/p.png'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('plate', UnlinedLining::sole()->kind);
        $this->assertSame(BulkUploadService::PRIORITY['UL'], BulkUploadService::PRIORITY['ULP']);
    }

    public function test_the_configurator_offers_unlined_only_on_fabrics_that_have_it(): void
    {
        $this->fabric('Navy Twill');
        $this->fabric('Blue Stripe');
        $this->upload('UL_Unlined_Navy Twill.png', 'https://cdn.test/navy-unlined.png');

        SuitConfiguratorController::forgetCache();

        $byName = collect($this->getJson('/api/configurator')->assertOk()->json('data'))->keyBy('name');

        $navy = $byName['Navy Twill']['unlined_lining'];
        $this->assertSame('https://cdn.test/navy-unlined.png', $navy['image']);
        $this->assertSame('Unlined', $navy['type']['name']);
        $this->assertSame('https://cdn.test/unlined-tile.png', $navy['type']['diagram']);
        $this->assertSame(100, $navy['layer_index']);

        $this->assertNull($byName['Blue Stripe']['unlined_lining']);
        $this->assertNull($byName['Navy Twill']['unlined_plate']);

        $this->upload('ULP_Unlined Plate_Navy Twill.png', 'https://cdn.test/navy-plate.png');
        SuitConfiguratorController::forgetCache();

        $byName = collect($this->getJson('/api/configurator')->assertOk()->json('data'))->keyBy('name');
        $this->assertSame('https://cdn.test/navy-plate.png', $byName['Navy Twill']['unlined_plate']['image']);
        $this->assertSame('Unlined Plate', $byName['Navy Twill']['unlined_plate']['type']['name']);
        $this->assertSame('https://cdn.test/navy-unlined.png', $byName['Navy Twill']['unlined_lining']['image']);
        $this->assertNull($byName['Blue Stripe']['unlined_plate']);
    }

    public function test_switching_it_off_removes_it_from_the_configurator(): void
    {
        $this->fabric('Navy Twill');
        $this->upload('UL_Unlined_Navy Twill.png');

        UnlinedLining::sole()->update(['status' => false]);   // the model clears the configurator cache itself

        $this->assertNull($this->getJson('/api/configurator')->assertOk()->json('data.0.unlined_lining'));
    }
}
