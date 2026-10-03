<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\SuitConfiguratorController;
use App\Services\Cloudflare\CloudflareImages;
use App\Services\Cloudflare\CloudflareStream;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/*
 * Copies every image and video the database points at on Cloudinary across to
 * Cloudflare, then rewrites the addresses — so Cloudinary can be switched off
 * without a single render going missing.
 *
 * Cloudflare fetches each file itself; nothing is downloaded here. An image
 * keeps its Cloudinary path as its Cloudflare id ("homepage/01M2….png"), so a
 * path a form saved resolves on the new disk exactly as it did on the old one.
 *
 * Safe to stop and run again: what has been copied is recorded, and an id
 * Cloudflare already holds counts as copied. Without --execute it only reports.
 */
class MoveMediaToCloudflare extends Command
{
    protected $signature = 'media:move-to-cloudflare
        {--execute : Copy and rewrite for real; without it nothing changes}
        {--limit=0 : Copy at most this many images, for a trial run}';

    protected $description = 'Copy every Cloudinary image and video to Cloudflare and rewrite the addresses';

    /** Tables that hold framework state rather than content. */
    private const SKIP_TABLES = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'];

    private const CLOUDINARY = '~https?://res\.cloudinary\.com/[A-Za-z0-9_-]+/(image|video)/upload/(?:[a-z]_[^/]+/)*v\d+/([^\s"\'<>)\\\\?#]+)~';

    /* Cloudflare allows 1,200 API calls in 5 minutes; this keeps comfortably under. */
    private const BATCH = 6;

    private const SECONDS_PER_BATCH = 2.0;

    public function handle(CloudflareImages $images, CloudflareStream $stream): int
    {
        if (! $images->isConfigured()) {
            $this->error('Cloudflare is not configured: set CLOUDFLARE_ACCOUNT_ID, CLOUDFLARE_API_TOKEN and CLOUDFLARE_IMAGES_HASH.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $map = $this->loadMap();

        $this->info('Looking for Cloudinary addresses…');
        [$found, $where] = $this->scan();

        $imagesToCopy = collect($found)->filter(fn ($f) => $f['type'] === 'image' && ! isset($map[$f['url']]));
        $videosToCopy = collect($found)->filter(fn ($f) => $f['type'] === 'video' && ! isset($map[$f['url']]));

        $this->table(['Column', 'Rows'], collect($where)->map(fn ($rows, $col) => [$col, $rows])->values()->all());
        $this->line(sprintf(
            '%d distinct addresses: %d images and %d videos still to copy, %d already done.',
            count($found), $imagesToCopy->count(), $videosToCopy->count(), count($found) - $imagesToCopy->count() - $videosToCopy->count()
        ));

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $imagesToCopy = $imagesToCopy->take($limit);
        }

        if (! $execute) {
            $minutes = ceil($imagesToCopy->count() / self::BATCH * self::SECONDS_PER_BATCH / 60);
            $this->newLine();
            $this->warn("Dry run — nothing was copied or changed. Copying would take about {$minutes} minutes.");
            $this->line('Run again with --execute to do it (add --limit=20 for a short trial first).');

            return self::SUCCESS;
        }

        $failures = $this->copyImages($images, $imagesToCopy->values()->all(), $map);

        foreach ($videosToCopy as $video) {
            $failures += $this->copyVideo($stream, $video, $map);
        }

        $this->saveMap($map);

        $rewritten = $this->rewrite($map);
        SuitConfiguratorController::forgetCache();

        $this->newLine();
        $this->info("Rewrote {$rewritten} rows to Cloudflare addresses.");

        if ($failures) {
            $this->warn("{$failures} could not be copied and still point at Cloudinary — see above. Run the command again to retry them.");

            return self::FAILURE;
        }

        $remaining = count($this->scan()[0]);
        $remaining === 0
            ? $this->info('Nothing points at Cloudinary any more.')
            : $this->warn("{$remaining} addresses still point at Cloudinary (a --limit run, or a retry is needed).");

        return self::SUCCESS;
    }

    /**
     * Every distinct Cloudinary address in the database, written plainly or
     * with escaped slashes inside JSON.
     *
     * @return array{0: array<string, array{url: string, type: string, path: string}>, 1: array<string, int>}
     */
    private function scan(): array
    {
        $found = [];
        $where = [];

        foreach ($this->textColumns() as [$table, $column]) {
            $rows = DB::table($table)->where($column, 'like', '%res.cloudinary.com%')->pluck($column);

            if ($rows->isEmpty()) {
                continue;
            }

            $where["{$table}.{$column}"] = $rows->count();

            foreach ($rows as $value) {
                preg_match_all(self::CLOUDINARY, str_replace('\\/', '/', (string) $value), $matches, PREG_SET_ORDER);

                foreach ($matches as [$url, $type, $path]) {
                    $found[$url] ??= ['url' => $url, 'type' => $type, 'path' => rawurldecode($path)];
                }
            }
        }

        return [$found, $where];
    }

    /**
     * @param  array<int, array{url: string, type: string, path: string}>  $queue
     */
    private function copyImages(CloudflareImages $images, array $queue, array &$map): int
    {
        if ($queue === []) {
            return 0;
        }

        $this->info('Copying '.count($queue).' images to Cloudflare…');
        $bar = $this->output->createProgressBar(count($queue));
        $failures = 0;

        $batches = array_chunk($queue, self::BATCH);

        foreach ($batches as $n => $batch) {
            $started = microtime(true);

            $items = [];
            foreach ($batch as $image) {
                $items[$image['path']] = $image['url'];
            }

            foreach ($images->copyMany($items) as $id => $outcome) {
                $source = $items[$id];

                if ($outcome === true) {
                    $map[$source] = $images->url($id);
                } else {
                    $failures++;
                    $this->newLine();
                    $this->error("  {$id}: {$outcome}");
                }
            }

            $bar->advance(count($batch));

            /* Record as we go, so a stopped run picks up where it left off. */
            $this->saveMap($map);

            /* Pace only between batches; there is nothing to wait for after the last. */
            $spare = self::SECONDS_PER_BATCH - (microtime(true) - $started);
            if ($spare > 0 && $n < count($batches) - 1) {
                usleep((int) ($spare * 1_000_000));
            }
        }

        $bar->finish();
        $this->newLine();

        return $failures;
    }

    /**
     * A video is re-encoded by Stream before its MP4 exists, so this waits.
     *
     * @param  array{url: string, type: string, path: string}  $video
     */
    private function copyVideo(CloudflareStream $stream, array $video, array &$map): int
    {
        $this->info("Copying video {$video['path']} to Cloudflare Stream…");

        try {
            $uid = $stream->copyFromUrl($video['url'], $video['path'])['uid'];

            for ($waited = 0; $waited <= 900; $waited += 5) {
                $mp4 = $stream->mp4($uid);

                if ($mp4['state'] === 'ready' && $mp4['url']) {
                    $map[$video['url']] = $mp4['url'];
                    $this->line("  ready: {$mp4['url']}");

                    return 0;
                }

                $this->line(sprintf('  %s %d%%…', $mp4['state'], $mp4['percent']));
                sleep(5);
            }

            $this->error("  Stream was still working on {$uid} after 15 minutes; run the command again to pick it up.");
        } catch (Throwable $e) {
            $this->error("  {$e->getMessage()}");
        }

        return 1;
    }

    /** Swap every copied address in place, plain or JSON-escaped. */
    private function rewrite(array $map): int
    {
        if ($map === []) {
            return 0;
        }

        $plain = $map;
        $escaped = [];
        foreach ($map as $old => $new) {
            $escaped[str_replace('/', '\\/', $old)] = str_replace('/', '\\/', $new);
        }

        $rewritten = 0;

        foreach ($this->textColumns() as [$table, $column]) {
            if (! Schema::hasColumn($table, 'id')) {
                continue;
            }

            DB::table($table)
                ->where($column, 'like', '%res.cloudinary.com%')
                ->select(['id', $column])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $column, $plain, $escaped, &$rewritten) {
                    foreach ($rows as $row) {
                        $value = strtr(strtr((string) $row->{$column}, $escaped), $plain);

                        if ($value !== $row->{$column}) {
                            DB::table($table)->where('id', $row->id)->update([$column => $value]);
                            $rewritten++;
                        }
                    }
                });
        }

        return $rewritten;
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function textColumns(): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn ($table) => in_array($table, self::SKIP_TABLES, true))
            ->flatMap(fn ($table) => collect(Schema::getColumns($table))
                ->filter(fn ($column) => preg_match('/char|text|json/i', $column['type_name']))
                ->map(fn ($column) => [$table, $column['name']]))
            ->values()
            ->all();
    }

    private function mapPath(): string
    {
        return storage_path('app/cloudflare-migration.json');
    }

    /** @return array<string, string> Cloudinary address => Cloudflare address */
    private function loadMap(): array
    {
        return is_file($this->mapPath())
            ? json_decode(file_get_contents($this->mapPath()), true) ?: []
            : [];
    }

    private function saveMap(array $map): void
    {
        file_put_contents($this->mapPath(), json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
