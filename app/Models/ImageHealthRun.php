<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/* One pass of the image health check. */
class ImageHealthRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cloudflare_checked' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public static function latestFinished(): ?self
    {
        return static::query()->whereNotNull('finished_at')->latest('id')->first();
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ImageIssue::class);
    }

    public function seconds(): ?float
    {
        return $this->started_at && $this->finished_at ? round($this->started_at->floatDiffInSeconds($this->finished_at), 1) : null;
    }
}
