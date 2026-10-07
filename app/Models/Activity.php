<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/*
 * One thing an administrator did. Nobody edits or deletes entries from the
 * admin, a super admin included: the log is only worth anything if it cannot
 * be rewritten. (The one thing that touches an entry again is the logger,
 * folding a bulk upload's later chunks into its batch line.)
 */
class Activity extends Model
{
    protected $table = 'activity_log';

    const UPDATED_AT = null;

    protected $guarded = [];

    /** label, badge colour, icon */
    public const EVENTS = [
        'created' => ['Created', 'success', 'heroicon-m-plus-circle'],
        'updated' => ['Updated', 'info', 'heroicon-m-pencil-square'],
        'deleted' => ['Deleted', 'danger', 'heroicon-m-trash'],
        'reordered' => ['Reordered', 'primary', 'heroicon-m-arrows-up-down'],
        'bulk_upload' => ['Bulk upload', 'primary', 'heroicon-m-cloud-arrow-up'],
        'login' => ['Signed in', 'gray', 'heroicon-m-arrow-right-end-on-rectangle'],
        'logout' => ['Signed out', 'gray', 'heroicon-m-arrow-left-start-on-rectangle'],
        'login_failed' => ['Failed sign-in', 'warning', 'heroicon-m-shield-exclamation'],
        'imported' => ['Imported', 'success', 'heroicon-m-arrow-down-tray'],
        'refreshed' => ['Refreshed', 'info', 'heroicon-m-arrow-path'],
        'restored' => ['Restored', 'warning', 'heroicon-m-arrow-uturn-left'],
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function eventOptions(): array
    {
        return array_map(fn (array $event) => $event[0], self::EVENTS);
    }

    public function eventLabel(): string
    {
        return self::EVENTS[$this->event][0] ?? Str::headline($this->event);
    }

    public function eventColor(): string
    {
        return self::EVENTS[$this->event][1] ?? 'gray';
    }

    public function eventIcon(): string
    {
        return self::EVENTS[$this->event][2] ?? 'heroicon-m-bolt';
    }

    public static function typeLabel(?string $type): ?string
    {
        return $type ? Str::of(class_basename($type))->snake(' ')->ucfirst()->toString() : null;
    }

    public function subjectTypeLabel(): ?string
    {
        return self::typeLabel($this->subject_type);
    }

    /** "Updated Fabric “Navy Twill”" — the line a person reads. */
    public function sentence(): string
    {
        return match ($this->event) {
            'login' => 'Signed in',
            'logout' => 'Signed out',
            'login_failed' => 'Failed sign-in as '.($this->subject_label ?? 'unknown'),
            'imported' => 'Imported “'.$this->subject_label.'” from '.($this->properties['source'] ?? 'outside'),
            'refreshed' => 'Refreshed “'.$this->subject_label.'” from '.($this->properties['source'] ?? 'outside'),
            'restored' => 'Restored '.strtolower($this->subjectTypeLabel() ?? 'record').' “'.$this->subject_label.'” to an earlier version',
            'reordered' => 'Reordered '.Str::plural(strtolower($this->subjectTypeLabel() ?? 'item'), (int) ($this->properties['count'] ?? 2)),
            'bulk_upload' => 'Bulk uploaded '.($this->properties['filed'] ?? 0).' '.Str::plural('file', (int) ($this->properties['filed'] ?? 0)),
            default => trim($this->eventLabel().' '.strtolower($this->subjectTypeLabel() ?? '')
                .($this->subject_label ? ' “'.$this->subject_label.'”' : '')),
        };
    }

    /**
     * The fields an entry touched, as rows of field / before / after.
     *
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    public function changes(): array
    {
        $properties = $this->properties ?? [];

        if (isset($properties['new'])) {
            return collect($properties['new'])
                ->map(fn ($new, $field) => ['field' => $field, 'old' => $properties['old'][$field] ?? null, 'new' => $new])
                ->values()->all();
        }

        if (isset($properties['attributes'])) {
            $deleted = $this->event === 'deleted';

            return collect($properties['attributes'])
                ->map(fn ($value, $field) => ['field' => $field, 'old' => $deleted ? $value : null, 'new' => $deleted ? null : $value])
                ->values()->all();
        }

        return [];
    }

    /** "Chrome on Windows", read loosely from the user agent. Order matters: Edge says Chrome, Chrome says Safari, iPhones say Mac OS. */
    public function device(): ?string
    {
        $agent = (string) $this->user_agent;

        if ($agent === '') {
            return null;
        }

        $browser = collect(['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Chrome/' => 'Chrome', 'Firefox/' => 'Firefox', 'Safari/' => 'Safari'])
            ->first(fn (string $label, string $needle) => str_contains($agent, $needle));

        $system = collect(['Windows' => 'Windows', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Mac OS' => 'macOS', 'Linux' => 'Linux'])
            ->first(fn (string $label, string $needle) => str_contains($agent, $needle));

        return trim(($browser ?? 'Unknown browser').($system ? ' on '.$system : '')) ?: null;
    }

    public function changeCount(): int
    {
        return count($this->changes());
    }
}
