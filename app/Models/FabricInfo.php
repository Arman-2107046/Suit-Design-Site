<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/*
 * The "more info" card for a fabric: a title, up to ten icon badges, and
 * four columns of "Label: value(note)" entries typed as plain text.
 */
class FabricInfo extends Model
{
    use BustsConfiguratorCache;

    public const MAX_BADGES = 10;

    public const COLUMNS = ['column_1', 'column_2', 'column_3', 'column_4'];

    protected $fillable = ['fabric_id', 'title', 'description', 'badges', 'column_1', 'column_2', 'column_3', 'column_4'];

    protected $casts = [
        'badges' => 'array',
    ];

    protected static function booted(): void
    {
        // Resolve icon URLs once, when a badge's icon changes; drop icons that were removed.
        static::saving(function (FabricInfo $info) {
            $previous = collect($info->getOriginal('badges') ?? [])->keyBy('icon');
            $badges = [];

            foreach ($info->badges ?? [] as $badge) {
                if (blank($badge['icon'] ?? null) && blank($badge['name'] ?? null)) {
                    continue;
                }
                $icon = $badge['icon'] ?? null;
                $badges[] = [
                    'icon' => $icon,
                    'icon_url' => filled($icon) ? ($previous[$icon]['icon_url'] ?? Storage::disk('cloudinary')->url($icon)) : null,
                    'name' => trim((string) ($badge['name'] ?? '')),
                ];
            }

            $info->badges = array_slice($badges, 0, self::MAX_BADGES);

            foreach ($previous->keys()->filter()->diff(collect($badges)->pluck('icon')->filter()) as $removed) {
                rescue(fn () => Storage::disk('cloudinary')->delete($removed), report: false);
            }
        });

        static::deleted(function (FabricInfo $info) {
            foreach (collect($info->badges ?? [])->pluck('icon')->filter() as $icon) {
                rescue(fn () => Storage::disk('cloudinary')->delete($icon), report: false);
            }
        });
    }

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    /**
     * "Tone: Black, Weave: Twill(Twill is a diagonal weave, durable), Occasion: Business, Casual"
     * -> [{label, value, note}]. Commas inside (…) never split; a chunk without a colon
     * continues the previous value, so lists like "Business, Casual" stay together.
     *
     * @return array<int, array{label: string, value: string, note: ?string}>
     */
    public static function parseColumn(?string $text): array
    {
        $entries = [];
        $depth = 0;
        $chunk = '';
        $chunks = [];

        foreach (mb_str_split((string) $text) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            }
            if ($char === ',' && $depth === 0) {
                $chunks[] = $chunk;
                $chunk = '';
                continue;
            }
            $chunk .= $char;
        }
        $chunks[] = $chunk;

        foreach ($chunks as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }

            preg_match('/^(.*?)\((.*)\)\s*$/s', $raw, $m);
            $body = trim($m[1] ?? $raw);
            $note = isset($m[2]) ? trim($m[2]) : null;

            if (str_contains($body, ':')) {
                [$label, $value] = array_map('trim', explode(':', $body, 2));
                $entries[] = ['label' => $label, 'value' => $value, 'note' => $note ?: null];
            } elseif ($entries) {
                $last = array_key_last($entries);
                $entries[$last]['value'] = trim($entries[$last]['value'] . ', ' . $body, ', ');
                $entries[$last]['note'] = $entries[$last]['note'] ?? ($note ?: null);
            }
        }

        return $entries;
    }

    /** @return array<string, mixed> */
    public function toCardArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'badges' => collect($this->badges ?? [])
                ->map(fn ($b) => ['name' => $b['name'] ?? '', 'icon' => $b['icon_url'] ?? null])
                ->filter(fn ($b) => $b['name'] !== '' || $b['icon'])
                ->values()
                ->all(),
            'columns' => array_map(fn ($column) => self::parseColumn($this->{$column}), self::COLUMNS),
        ];
    }
}
