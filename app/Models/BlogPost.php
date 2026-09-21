<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    protected $fillable = [
        'user_id', 'blog_category_id', 'title', 'slug', 'excerpt', 'cover_image', 'cover_image_url', 'cover_caption',
        'body', 'tags', 'status', 'published_at', 'is_featured', 'reading_minutes', 'seo_title', 'seo_description', 'views',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (BlogPost $post) {
            $post->slug = Str::slug($post->slug ?: $post->title);
            $post->reading_minutes = max(1, (int) ceil(str_word_count(strip_tags((string) $post->body)) / 200));

            if ($post->status === 'published' && ! $post->published_at) {
                $post->published_at = now();
            }

            if ($post->isDirty('cover_image')) {
                $previous = $post->getOriginal('cover_image');
                $post->cover_image_url = filled($post->cover_image) ? Storage::disk('cloudinary')->url($post->cover_image) : null;
                if (filled($previous) && $previous !== $post->cover_image) {
                    rescue(fn () => Storage::disk('cloudinary')->delete($previous), report: false);
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function fabrics(): BelongsToMany
    {
        return $this->belongsToMany(Fabric::class, 'blog_post_fabric')->orderBy('sort_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->where('status', 'approved')->oldest();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(BlogLike::class);
    }

    public function excerptText(): string
    {
        return $this->excerpt ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->body))), 180);
    }

    /** Card data for lists and the homepage. */
    public function toCardArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerptText(),
            'cover' => $this->cover_image_url,
            'category' => $this->category?->only(['name', 'slug']),
            'author' => $this->author?->name,
            'published_at' => $this->published_at?->toIso8601String(),
            'reading_minutes' => $this->reading_minutes,
            'is_featured' => $this->is_featured,
            'likes' => $this->likes_count ?? $this->likes()->count(),
            'comments' => $this->approved_comments_count ?? $this->approvedComments()->count(),
        ];
    }
}
