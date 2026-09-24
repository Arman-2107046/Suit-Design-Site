<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkUploadReportTest extends TestCase
{
    use RefreshDatabase;

    private function rejects(): array
    {
        return [
            'rejected' => [
                ['name' => 'FPI_Nope_1.png', 'stage' => 'Filing', 'reason' => 'Fabric "Nope" not found'],
                ['name' => 'huge.png', 'stage' => 'Cloudinary upload', 'reason' => 'File size too large'],
            ],
            'batch' => ['total' => 12, 'filed' => 10],
        ];
    }

    public function test_an_admin_gets_a_pdf_of_the_batch_rejections(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.bulk-upload.report'), $this->rejects());

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_the_same_batch_comes_back_as_a_csv(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.bulk-upload.report'), ['format' => 'csv'] + $this->rejects());

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();

        $this->assertSame(chr(239).chr(187).chr(191), substr($csv, 0, 3), 'Excel needs the BOM to read it as UTF-8');
        $this->assertStringContainsString('#,File,"Rejected at",Reason', $csv);
        $this->assertStringContainsString('FPI_Nope_1.png', $csv);
        $this->assertStringContainsString('File size too large', $csv);
    }

    public function test_a_filename_that_looks_like_a_formula_cannot_run_in_a_spreadsheet(): void
    {
        $csv = $this->actingAs(User::factory()->create())
            ->postJson(route('admin.bulk-upload.report'), [
                'format' => 'csv',
                'rejected' => [
                    ['name' => '=HYPERLINK("http://evil.test")', 'stage' => 'Filing', 'reason' => 'Unknown prefix'],
                ],
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(' =HYPERLINK', $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_the_report_is_closed_to_guests(): void
    {
        $this->postJson(route('admin.bulk-upload.report'), $this->rejects())
            ->assertUnauthorized();
    }

    public function test_an_empty_batch_has_nothing_to_report(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.bulk-upload.report'), ['rejected' => []])
            ->assertStatus(422);
    }
}
