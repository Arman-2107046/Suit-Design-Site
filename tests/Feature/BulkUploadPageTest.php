<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BulkUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkUploadPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_uploader_renders_with_the_filing_order_and_no_time_estimate(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get('/admin/bulk-upload')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="bx"', $html);
        $this->assertStringContainsString('window.bulkUploadQueue = window.bulkUploadQueue', $html);

        /* The browser sorts by the same order the server files in */
        $this->assertStringContainsString('\u0022FAB\u0022:1', $html);
        $this->assertStringContainsString('\u0022LP\u0022:19', $html);

        $this->assertStringNotContainsString('estimating time left', $html);
    }

    public function test_a_fabric_is_filed_before_its_pictures(): void
    {
        $this->assertLessThan(
            BulkUploadService::priorityOf('FPI_Navy Twill_1.png'),
            BulkUploadService::priorityOf('FAB_240_Navy Twill.png')
        );
    }

    public function test_a_lapel_is_filed_after_its_category_and_width(): void
    {
        $category = BulkUploadService::priorityOf('LPC_Peak.png');
        $width = BulkUploadService::priorityOf('LPS_Standard.png');
        $lapel = BulkUploadService::priorityOf('LP_SB1_Peak_Standard_Navy.png');

        $this->assertLessThan($width, $category);
        $this->assertLessThan($lapel, $width);
    }

    public function test_an_unknown_prefix_sorts_last_instead_of_breaking_the_sort(): void
    {
        $this->assertSame(999, BulkUploadService::priorityOf('holiday-photo.png'));
    }
}
