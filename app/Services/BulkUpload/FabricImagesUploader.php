<?php

namespace App\Services\BulkUpload;

use App\Models\Fabric;
use App\Models\FabricImage;

/*
 * Gallery pictures for a fabric.
 *
 *   FPI_Blue Stripe.png      preview picture, appended
 *   FPI_Blue Stripe_3.png    preview picture in slot 3 (replaces slot 3 if taken)
 *   RL_Blue Stripe_1.png     real-life picture, slot 1 (FRL_ also accepted)
 *   RL_Blue Stripe_2 Piece Suit.png     real-life picture captioned "2 Piece Suit"
 *   RL_Blue Stripe_2_Waistcoat.png      slot 2, captioned "Waistcoat"
 *
 * Fabric names must not contain underscores. Up to 10 pictures per kind and fabric.
 */
class FabricImagesUploader
{
    private const KINDS = ['FPI' => 'preview', 'RL' => 'real_life', 'FRL' => 'real_life'];

    public function handle(array $file): array
    {
        $originalName = $file['name'] ?? null;
        $url = $file['url'] ?? null;

        if (! $originalName || ! $url) {
            return ['success' => false, 'message' => 'Invalid file data'];
        }

        $filename = pathinfo($originalName, PATHINFO_FILENAME);

        if (! preg_match('/^(FPI|RL|FRL)_([^_]+?)(?:_(\d{1,2}))?(?:_(.+))?$/i', $filename, $m)) {
            return [
                'success' => false,
                'message' => "{$originalName} — expected FPI_Fabric Name.png (preview) or RL_Fabric Name.png (real life), optionally with _2 for the slot",
            ];
        }

        $kind = self::KINDS[strtoupper($m[1])];
        $fabricName = trim($m[2]);
        $slot = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;
        $caption = isset($m[4]) ? trim($m[4]) : null;

        $fabric = Fabric::where('name', $fabricName)->first();

        if (! $fabric) {
            return [
                'success' => false,
                'message' => "{$originalName} — fabric \"{$fabricName}\" not found",
                'debug' => ['searched_column' => 'fabrics.name', 'fabric_name' => $fabricName],
            ];
        }

        $existing = $fabric->images()->where('kind', $kind)->get();

        if ($slot !== null && ($replace = $existing->firstWhere('sort_order', $slot))) {
            $replace->update(['url' => $url, ...($caption ? ['caption' => $caption] : [])]);

            return ['success' => true, 'message' => "{$originalName} — replaced picture {$slot} of {$fabric->name}"];
        }

        if ($existing->count() >= FabricImage::MAX_PER_KIND) {
            return [
                'success' => false,
                'message' => "{$originalName} — {$fabric->name} already has " . FabricImage::MAX_PER_KIND . " {$kind} pictures",
            ];
        }

        $fabric->images()->create([
            'kind' => $kind,
            'url' => $url,
            'caption' => $caption,
            'sort_order' => $slot ?? ((int) $existing->max('sort_order') + 1),
        ]);

        return ['success' => true, 'message' => "{$originalName} — added to {$fabric->name}"];
    }
}
