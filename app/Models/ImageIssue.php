<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* A picture the last health check could not load, and where to fix it. */
class ImageIssue extends Model
{
    protected $guarded = [];

    /** reason => [short label, what it means] */
    public const REASONS = [
        'missing' => ['No picture', 'Nothing has been uploaded for this.'],
        'not_on_cloudflare' => ['Not on Cloudflare', 'Your Cloudflare Images account no longer has this picture, or its upload never finished.'],
        'refused' => ['Account refused it', 'The host refuses to serve it (401/403). Pictures on the switched-off Cloudinary account fail like this.'],
        'not_found' => ['Not found', 'The address no longer exists (404).'],
        'server_error' => ['Host error', 'The image server failed (5xx). It may be temporary; check again later.'],
        'unreachable' => ['Unreachable', 'The image server could not be reached.'],
        'other' => ['Did not load', 'The server answered, but not with the picture.'],
    ];

    protected function casts(): array
    {
        return ['hidden' => 'boolean'];
    }

    public static function reasonOptions(): array
    {
        return array_map(fn (array $r) => $r[0], self::REASONS);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason][0] ?? $this->reason;
    }

    public function reasonText(): string
    {
        $text = self::REASONS[$this->reason][1] ?? '';

        return $this->reason === 'other' && $this->http_status ? "The server answered with HTTP {$this->http_status}." : $text;
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ImageHealthRun::class, 'image_health_run_id');
    }
}
