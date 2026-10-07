<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/* The RankYak connection: one row, made on first use. */
class RankYakSetting extends Model
{
    protected $table = 'rankyak_settings';

    public const PUBLISH = 'publish';

    public const DRAFT = 'draft';

    protected $fillable = [
        'enabled', 'api_key', 'publish_mode', 'blog_category_id', 'admin_id', 'copy_images', 'report_urls',
    ];

    protected $hidden = ['api_key', 'webhook_token'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'api_key' => 'encrypted',
            'copy_images' => 'boolean',
            'report_urls' => 'boolean',
            'last_webhook_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->forceCreate(['webhook_token' => self::newToken()]);
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    /** Where RankYak should send new articles. The token in the path is the only key to it. */
    public function webhookUrl(): string
    {
        return route('webhooks.rankyak', $this->webhook_token);
    }

    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }

    public function tokenMatches(string $token): bool
    {
        return hash_equals($this->webhook_token, $token);
    }

    public function recordError(string $message): void
    {
        $this->forceFill(['last_error' => Str::limit($message, 490), 'last_error_at' => now()])->save();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
