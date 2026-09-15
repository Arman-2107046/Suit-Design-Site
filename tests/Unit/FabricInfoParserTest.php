<?php

namespace Tests\Unit;

use App\Models\FabricInfo;
use PHPUnit\Framework\TestCase;

class FabricInfoParserTest extends TestCase
{
    public function test_parses_labels_values_and_bracketed_notes(): void
    {
        $entries = FabricInfo::parseColumn('Tone: Black, Weave: Twill(Twill is a diagonal weave, durable and forgiving.), Brand: Loro Piana');

        $this->assertSame([
            ['label' => 'Tone', 'value' => 'Black', 'note' => null],
            ['label' => 'Weave', 'value' => 'Twill', 'note' => 'Twill is a diagonal weave, durable and forgiving.'],
            ['label' => 'Brand', 'value' => 'Loro Piana', 'note' => null],
        ], $entries);
    }

    public function test_a_chunk_without_a_colon_continues_the_previous_value(): void
    {
        $entries = FabricInfo::parseColumn('Suggested occasion: Business, Smart casual, Casual, Stretch: Comfort stretch(Moves with you.)');

        $this->assertCount(2, $entries);
        $this->assertSame('Business, Smart casual, Casual', $entries[0]['value']);
        $this->assertSame('Comfort stretch', $entries[1]['value']);
        $this->assertSame('Moves with you.', $entries[1]['note']);
    }

    public function test_empty_and_stray_input_is_ignored(): void
    {
        $this->assertSame([], FabricInfo::parseColumn(null));
        $this->assertSame([], FabricInfo::parseColumn('  ,, no colon here'));
        // Any bracket becomes the (i) note, by design — write "Medium 280 gr/m²" to keep it inline.
        $this->assertSame([['label' => 'Weight', 'value' => 'Medium', 'note' => '280 gr/m²']], FabricInfo::parseColumn('Weight: Medium (280 gr/m²)'));
    }
}
