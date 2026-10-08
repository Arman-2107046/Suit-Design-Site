<?php

namespace Tests\Feature;

use App\Filament\Pages\BulkUpload;
use App\Models\Admin;
use App\Services\BulkUpload\NamingGuide;
use App\Services\BulkUploadService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkUploadNamingGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_prefix_the_uploader_files_by_is_explained(): void
    {
        $explained = array_column(NamingGuide::rules(), 'prefix');

        /* FRL is only another spelling of RL, said in RL's notes. */
        foreach (array_diff(array_keys(BulkUploadService::PRIORITY), ['FRL']) as $prefix) {
            $this->assertContains($prefix, $explained, "{$prefix} has no naming rule");
        }

        $this->assertEmpty(array_diff($explained, array_keys(BulkUploadService::PRIORITY)), 'a rule names a prefix the uploader does not know');
    }

    public function test_each_example_filename_starts_with_its_own_prefix_and_pattern(): void
    {
        foreach (NamingGuide::rules() as $rule) {
            $this->assertSame($rule['order'], BulkUploadService::priorityOf($rule['example']), "{$rule['example']} would be filed as another kind");
            $this->assertStringStartsWith($rule['prefix'].'_', $rule['pattern']);
            $this->assertStringStartsWith($rule['prefix'].'_', $rule['example']);
        }
    }

    public function test_the_rules_follow_the_order_files_are_filed_in(): void
    {
        $order = array_column(NamingGuide::rules(), 'order');
        $sorted = $order;
        sort($sorted);

        $this->assertSame($sorted, $order);
    }

    public function test_the_page_lists_the_rules_and_offers_the_pdf(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(BulkUpload::class)
            ->assertSee('Naming conventions')
            ->assertSee('Download PDF')
            ->assertSee('LP_BodyCode_Style_Width_Fabric[_1]')
            ->assertSee('FAB_120_Blue Stripe.png')
            ->assertSeeHtml(route('admin.bulk-upload.naming-guide'));
    }

    public function test_an_admin_downloads_the_guide_as_a_pdf(): void
    {
        $response = $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('admin.bulk-upload.naming-guide'))
            ->assertOk()
            ->assertDownload('bulk-upload-naming-conventions.pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_guide_is_closed_to_guests(): void
    {
        $this->getJson(route('admin.bulk-upload.naming-guide'))->assertUnauthorized();
    }
}
