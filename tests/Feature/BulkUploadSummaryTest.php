<?php

namespace Tests\Feature;

use App\Filament\Pages\BulkUpload;
use App\Models\Fabric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkUploadSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_completion_summary_counts_what_landed_where(): void
    {
        Fabric::create(['name' => 'Blue Stripe', 'price' => 120, 'image' => 'https://example.test/s.png', 'is_default' => true, 'status' => true]);

        $summary = app(BulkUpload::class)->processUploads([
            ['name' => 'FPI_Blue Stripe_1.png', 'url' => 'https://cdn/1.png'],
            ['name' => 'FPI_Blue Stripe_2.png', 'url' => 'https://cdn/2.png'],
            ['name' => 'RL_Blue Stripe_1_2 Piece Suit.png', 'url' => 'https://cdn/3.png'],
            ['name' => 'FAB_240_Charcoal Twill.png', 'url' => 'https://cdn/4.png'],
        ]);

        $this->assertSame(4, $summary['filed']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(['fabric images' => 3, 'fabrics' => 1], $summary['breakdown']);
    }

    public function test_files_that_could_not_be_filed_are_kept_out_of_the_breakdown(): void
    {
        $summary = app(BulkUpload::class)->processUploads([
            ['name' => 'FPI_Nope_1.png', 'url' => 'https://cdn/x.png'],
            ['name' => 'ZZZ_Mystery.png', 'url' => 'https://cdn/y.png'],
        ]);

        $this->assertSame(0, $summary['filed']);
        $this->assertSame(2, $summary['failed']);
        $this->assertSame([], $summary['breakdown']);
    }
}
