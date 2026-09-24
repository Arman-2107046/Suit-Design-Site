<?php

namespace Tests\Feature;

use App\Models\Fabric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkUploadSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function file(array $files): array
    {
        return $this->actingAs(User::factory()->create())
            ->postJson(route('admin.bulk-upload.process'), ['files' => $files])
            ->assertOk()
            ->json();
    }

    public function test_the_completion_summary_counts_what_landed_where(): void
    {
        Fabric::create(['name' => 'Blue Stripe', 'price' => 120, 'image' => 'https://example.test/s.png', 'is_default' => true, 'status' => true]);

        $summary = $this->file([
            ['name' => 'FPI_Blue Stripe_1.png', 'url' => 'https://cdn.test/1.png'],
            ['name' => 'FPI_Blue Stripe_2.png', 'url' => 'https://cdn.test/2.png'],
            ['name' => 'RL_Blue Stripe_1_2 Piece Suit.png', 'url' => 'https://cdn.test/3.png'],
            ['name' => 'FAB_240_Charcoal Twill.png', 'url' => 'https://cdn.test/4.png'],
        ]);

        $this->assertSame(4, $summary['filed']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(['fabric images' => 3, 'fabrics' => 1], $summary['breakdown']);
    }

    public function test_files_that_could_not_be_filed_come_back_with_their_reasons(): void
    {
        $summary = $this->file([
            ['name' => 'FPI_Nope_1.png', 'url' => 'https://cdn.test/x.png'],
            ['name' => 'ZZZ_Mystery.png', 'url' => 'https://cdn.test/y.png'],
        ]);

        $this->assertSame(0, $summary['filed']);
        $this->assertSame(2, $summary['failed']);
        $this->assertSame([], $summary['breakdown']);
        $this->assertSame('FPI_Nope_1.png', $summary['rejected'][0]['file']);
        $this->assertStringContainsString('not found', $summary['rejected'][0]['reason']);
    }

    public function test_filing_a_batch_is_closed_to_guests(): void
    {
        $this->postJson(route('admin.bulk-upload.process'), [
            'files' => [['name' => 'FAB_240_Charcoal Twill.png', 'url' => 'https://cdn.test/4.png']],
        ])->assertUnauthorized();
    }
}
