<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;

/*
 * Single-row layout settings for the designer.
 *
 * The defaults are the layout the designer shipped with, so an untouched
 * install looks exactly as it did before these existed.
 */
class DesignerSetting extends Model
{
    use BustsConfiguratorCache;

    /** How the Style tab presents its option groups. */
    public const LAYOUTS = [
        'grid' => 'Grid of tiles (default)',
        'slider' => 'Horizontal slider',
        'list' => 'Vertical list',
        'dropdown' => 'Dropdown',
    ];

    public const COLUMN_CHOICES = [2, 3, 4, 5];

    protected $fillable = [
        'fabric_columns',
        'style_layout',
        'style_columns',
        'lining_columns',
    ];

    protected $casts = [
        'fabric_columns' => 'integer',
        'style_columns' => 'integer',
        'lining_columns' => 'integer',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * What the designer reads. Values are clamped here rather than trusted,
     * because the column count reaches the browser as a Tailwind class.
     *
     * @return array<string, mixed>
     */
    public function toLayoutArray(): array
    {
        return [
            'fabric_columns' => $this->columns($this->fabric_columns, 3),
            'style_layout' => array_key_exists($this->style_layout, self::LAYOUTS) ? $this->style_layout : 'grid',
            'style_columns' => $this->columns($this->style_columns, 3),
            'lining_columns' => $this->columns($this->lining_columns, 2),
        ];
    }

    private function columns(?int $value, int $fallback): int
    {
        return in_array($value, self::COLUMN_CHOICES, true) ? $value : $fallback;
    }
}
