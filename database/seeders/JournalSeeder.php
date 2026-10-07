<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Fabric;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/*
 * Fifty journal posts across six categories, so the journal reads as a living
 * magazine from day one. Covers are the shop's own fabric photographs, and each
 * post links the cloths it shows. Safe to run again: posts are matched by slug
 * and updated in place, never duplicated.
 *
 *   php artisan db:seed --class=JournalSeeder
 */
class JournalSeeder extends Seeder
{
    private const CATEGORIES = [
        'style-guides' => ['Style Guides', 'How to wear a tailored suit well, from first principles to the finishing touches.'],
        'fit' => ['Fit & Tailoring', 'What a good fit looks like, and how a made-to-measure suit gets there.'],
        'fabric' => ['Fabric & Cloth', 'Wools, weaves, weights and patterns: choosing the cloth your suit is cut from.'],
        'occasions' => ['Occasions', 'Weddings, interviews, black tie and everything between: what to wear, and why.'],
        'care' => ['Suit Care', 'Keep a good suit good for years: cleaning, pressing, storing and travelling.'],
        'atelier' => ['Behind the Seams', 'How our suits are designed, cut and made, and the craft behind each step.'],
    ];

    public function run(): void
    {
        $categories = [];
        foreach (array_keys(self::CATEGORIES) as $i => $slug) {
            [$name, $description] = self::CATEGORIES[$slug];
            $categories[$slug] = BlogCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'sort_order' => $i + 1],
            );
        }

        $author = Admin::query()->superAdmins()->orderBy('id')->first() ?? Admin::query()->orderBy('id')->first();
        $fabrics = Fabric::query()->where('status', true)->orderBy('id')->get(['id', 'name', 'image']);

        /* The same "random" numbers every run, so re-seeding does not reshuffle the journal */
        mt_srand(2107046);

        $posts = $this->posts();
        $count = count($posts);

        foreach ($posts as $i => $post) {
            $body = $this->body($post);
            $slug = Str::slug($post['title']);
            $cover = $fabrics->isNotEmpty() ? $fabrics[$i % $fabrics->count()] : null;

            /* Oldest first, roughly a week apart, the newest a couple of days ago */
            $publishedAt = now()->subDays(2 + ($count - 1 - $i) * 6 + mt_rand(0, 3))->setTime(mt_rand(8, 18), mt_rand(0, 59));

            $record = BlogPost::updateOrCreate(['slug' => $slug], [
                'admin_id' => $author?->id,
                'blog_category_id' => $categories[$post['category']]->id,
                'title' => $post['title'],
                'excerpt' => $post['excerpt'],
                'body' => $body,
                'tags' => $post['tags'],
                'status' => 'published',
                'published_at' => $publishedAt,
                'is_featured' => in_array($i, [$count - 1, $count - 6, $count - 14], true),
                /* Set here as well as by the model, in case a caller seeds without model events */
                'reading_minutes' => max(1, (int) ceil(str_word_count(strip_tags($body)) / 200)),
                'seo_title' => Str::limit($post['title'], 67, '…'),
                'seo_description' => Str::limit($post['excerpt'], 157, '…'),
                'views' => mt_rand(40, 2400),
            ]);

            /* The cover photo is the shop's own cloth, so link it (and a couple more) as the post's fabrics */
            if ($cover) {
                $record->forceFill([
                    'cover_image_url' => $cover->image,
                    'cover_caption' => preg_match('/^Fabric\s*\d+$/i', $cover->name) ? null : "Pictured: {$cover->name}",
                ])->saveQuietly();

                $linked = collect([$cover->id])
                    ->merge($fabrics->except($i % $fabrics->count())->random(min(2, max(0, $fabrics->count() - 1)))->pluck('id'))
                    ->unique()->values();
                $record->fabrics()->sync($linked);
            }
        }

        $this->command?->info("Journal: {$count} posts in ".count($categories).' categories.');
    }

    /**
     * An article from its parts, laid out as the journal styles it: a
     * standfirst, the sections, a tailor's tip after the second section, an
     * optional pull quote, the key takeaways, then questions and answers.
     */
    private function body(array $post): string
    {
        $paragraphs = fn (array $texts) => implode('', array_map(fn (string $t) => '<p>'.$this->inline($t).'</p>', $texts));

        $html = '<p class="lead">'.$this->inline($post['lead']).'</p>'.$paragraphs($post['intro'] ?? []);

        foreach ($post['sections'] as $i => $section) {
            $html .= '<h2>'.e($section['h']).'</h2>'.$paragraphs($section['p']);

            if (! empty($section['list'])) {
                $tag = ($section['ordered'] ?? false) ? 'ol' : 'ul';
                $html .= "<{$tag}>".implode('', array_map(fn (string $item) => '<li>'.$this->inline($item).'</li>', $section['list']))."</{$tag}>";
            }

            if (! empty($section['after'])) {
                $html .= $paragraphs((array) $section['after']);
            }

            if ($i === 1 && isset($post['tip'])) {
                $html .= '<aside class="tip"><span class="label">Tailor’s tip</span><p>'.$this->inline($post['tip']).'</p></aside>';
            }

            if ($i === 2 && isset($post['quote'])) {
                $html .= '<blockquote><p>'.e($post['quote']).'</p></blockquote>';
            }
        }

        if (! empty($post['takeaways'])) {
            $html .= '<aside class="takeaways"><span class="label">Key takeaways</span><ul>'
                .implode('', array_map(fn (string $item) => '<li>'.$this->inline($item).'</li>', $post['takeaways']))
                .'</ul></aside>';
        }

        if (! empty($post['faq'])) {
            $html .= '<section class="faq"><h2>Questions we are often asked</h2>';
            foreach ($post['faq'] as [$question, $answer]) {
                $html .= '<h3>'.e($question).'</h3><p>'.$this->inline($answer).'</p>';
            }
            $html .= '</section>';
        }

        return $html;
    }

    /** Escapes text, then allows **bold** for the few words that carry a paragraph. */
    private function inline(string $text): string
    {
        return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($text));
    }

    /**
     * Every article, one file per category in ./journal, dealt out in turn so
     * the newest posts span the categories rather than ending on one.
     *
     * @return list<array<string, mixed>>
     */
    private function posts(): array
    {
        $byCategory = [];
        foreach (array_keys(self::CATEGORIES) as $category) {
            $byCategory[$category] = array_map(
                fn (array $post) => ['category' => $category] + $post,
                require __DIR__."/journal/{$category}.php",
            );
        }

        $posts = [];
        for ($round = 0; $round < max(array_map('count', $byCategory)); $round++) {
            foreach ($byCategory as $list) {
                if (isset($list[$round])) {
                    $posts[] = $list[$round];
                }
            }
        }

        return $posts;
    }
}
