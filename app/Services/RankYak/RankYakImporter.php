<?php

namespace App\Services\RankYak;

use App\Models\BlogPost;
use App\Models\RankYakSetting;
use App\Services\Activity\ActivityLogger;
use App\Services\Cloudflare\CloudflareImages;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/*
 * Turns a RankYak article (from the webhook or the API) into a journal post.
 *
 * The same article always lands on the same post: a re-sent webhook, or a
 * refresh RankYak makes later, updates it rather than adding a duplicate.
 */
class RankYakImporter
{
    public const SOURCE = 'rankyak';

    public function __construct(
        private readonly RankYakSetting $settings,
        private readonly CloudflareImages $images,
    ) {}

    public static function make(): self
    {
        return new self(RankYakSetting::current(), app(CloudflareImages::class));
    }

    /**
     * @param  array<string, mixed>  $article
     * @param  bool  $onlyIfChanged  from a sync: leave a post alone unless RankYak changed the article since
     * @return array{post: BlogPost, created: bool, changed: bool}
     */
    public function import(array $article, bool $onlyIfChanged = false): array
    {
        $article = $this->validate($article);
        $externalId = (string) $article['id'];

        $post = BlogPost::query()->where('source', self::SOURCE)->where('external_id', $externalId)->first();
        $created = ! $post;
        $updatedAt = $this->date($article['updated_at'] ?? null);

        if ($post && $onlyIfChanged && (! $updatedAt || ($post->external_updated_at && $updatedAt->lte($post->external_updated_at)))) {
            return ['post' => $post, 'created' => false, 'changed' => false];
        }

        $post ??= new BlogPost(['source' => self::SOURCE, 'external_id' => $externalId]);

        $publishedAt = $this->date($article['published_at'] ?? null) ?? $this->date($article['publish_at'] ?? null) ?? now();

        $post->fill([
            'title' => Str::limit(trim($article['title']), 160, ''),
            'excerpt' => filled($article['excerpt'] ?? null) ? Str::limit(trim(strip_tags($article['excerpt'])), 297, '…') : null,
            'body' => $this->body($article),
            'seo_title' => filled($article['meta_title'] ?? null) ? Str::limit(trim($article['meta_title']), 70, '') : null,
            'seo_description' => filled($article['meta_description'] ?? null) ? Str::limit(trim($article['meta_description']), 160, '') : null,
        ]);

        if ($created) {
            $post->fill([
                'slug' => $this->uniqueSlug($article['slug'] ?? $article['title']),
                'blog_category_id' => $this->settings->blog_category_id,
                'admin_id' => $this->settings->admin_id,
                /* A future date keeps the post hidden until then: the journal only lists published_at <= now */
                'status' => $this->settings->publish_mode === RankYakSetting::DRAFT ? 'draft' : 'published',
                'published_at' => $publishedAt,
            ]);
        }

        $post->forceFill(['source' => self::SOURCE, 'external_id' => $externalId, 'external_updated_at' => $updatedAt ?? now()]);

        $changed = $created || $post->isDirty();

        /* A cover an admin chose by hand is theirs: a refresh from RankYak does not replace it */
        $coverIsRankYaks = blank($post->cover_image_url)
            || str_starts_with((string) $post->cover_image, 'journal/rankyak-')
            || (blank($post->cover_image) && str_contains((string) $post->cover_image_url, 'rankyak'));

        ActivityLogger::quietly(function () use ($post, $article, $coverIsRankYaks) {
            $post->save();

            if ($coverIsRankYaks && filled($article['header_image_url'] ?? null)) {
                $this->attachCover($post, $article['header_image_url']);
            }
        });

        if ($changed) {
            ActivityLogger::log($created ? 'imported' : 'refreshed', $post, ['source' => 'RankYak', 'rankyak_id' => $externalId], actorName: 'RankYak');
        }

        return ['post' => $post, 'created' => $created, 'changed' => $changed];
    }

    /**
     * Tell RankYak where a post went live, once it is live. Quietly does nothing
     * without an API key, or while the post is a draft or still scheduled.
     */
    public function reportUrl(BlogPost $post): bool
    {
        if (! $this->settings->report_urls || ! $this->settings->hasApiKey() || $post->source !== self::SOURCE
            || $post->status !== 'published' || ! $post->published_at || $post->published_at->isFuture()) {
            return false;
        }

        (new RankYakClient($this->settings->api_key))->reportUrl($post->external_id, route('journal.show', $post->slug));

        $post->forceFill(['external_url_reported_at' => now()])->saveQuietly();

        return true;
    }

    /** @return array<string, mixed> */
    private function validate(array $article): array
    {
        $errors = [];

        if (blank($article['id'] ?? null) || ! is_scalar($article['id'])) {
            $errors['id'] = 'The article has no id.';
        }
        if (blank($article['title'] ?? null) || ! is_string($article['title'])) {
            $errors['title'] = 'The article has no title.';
        }
        if (blank($article['html'] ?? null) && blank($article['markdown'] ?? null)) {
            $errors['html'] = 'The article has no content.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $article;
    }

    private function body(array $article): string
    {
        $html = filled($article['html'] ?? null)
            ? (string) $article['html']
            : Str::markdown((string) $article['markdown'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        /* The page prints the title itself; drop a leading h1 that repeats it */
        $html = preg_replace('#^\s*<h1\b[^>]*>.*?</h1>\s*#is', '', $html, 1);

        return HtmlSanitizer::clean($html);
    }

    /**
     * Keep RankYak's slug for its SEO, unless another post already uses it.
     */
    private function uniqueSlug(string $slug): string
    {
        $base = Str::limit(Str::slug($slug), 150, '') ?: 'article';
        $candidate = $base;

        for ($n = 2; BlogPost::query()->where('slug', $candidate)->exists(); $n++) {
            $candidate = "{$base}-{$n}";
        }

        return $candidate;
    }

    /**
     * Copy the header image onto our own Cloudflare Images, so it is resized
     * and served like every other picture on the site. If that is off or
     * fails, the post still gets RankYak's own image address.
     */
    private function attachCover(BlogPost $post, string $url): void
    {
        if (! preg_match('#^https://#i', $url)) {
            return;
        }

        $id = 'journal/rankyak-'.Str::slug($post->external_id).'-'.substr(sha1($url), 0, 10);

        if ($post->cover_image === $id) {
            return;   // already copied this exact image
        }

        if ($this->settings->copy_images && $this->images->isConfigured()) {
            try {
                $this->images->uploadFromUrl($url, $id);   // false means it is already there, which is fine
                $post->cover_image = $id;                  // the model works out the address and drops the old one
                $post->save();

                return;
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($post->cover_image_url !== $url) {
            /* Quietly: the model would otherwise blank the address on seeing cover_image cleared */
            $post->forceFill(['cover_image' => null, 'cover_image_url' => $url])->saveQuietly();
        }
    }

    private function date(mixed $value): ?Carbon
    {
        if (blank($value) || ! is_string($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
