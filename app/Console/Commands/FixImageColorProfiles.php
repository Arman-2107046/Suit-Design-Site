<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ScansContentColumns;
use App\Http\Controllers\Api\SuitConfiguratorController;
use App\Services\Cloudflare\CloudflareImages;
use App\Support\ColorProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/*
 * Gives every image already on Cloudflare the colour profile it was missing,
 * so renders exported in Adobe RGB stop looking washed out next to the rest.
 * App\Support\ColorProfile explains the problem; uploads are fixed as they
 * happen, and this catches up the images that went up before.
 *
 * Cloudflare cannot change an image in place, so a fixed copy goes up under
 * "srgb/<old id>" and the database is pointed at it. The new address also
 * means no browser or CDN keeps showing the faded version from its cache.
 * The originals stay on Cloudflare unless --delete-old is given: another copy
 * of the site (production, a teammate's machine) may still point at them.
 *
 * Safe to stop and run again: every image checked is recorded. Without
 * --execute it only reports.
 */
class FixImageColorProfiles extends Command
{
    use ScansContentColumns;

    protected $signature = 'media:fix-color-profiles
        {--execute : Upload the fixed copies and rewrite the addresses; without it nothing changes}
        {--limit=0 : Check at most this many images, for a trial run}
        {--delete-old : Also delete each original from Cloudflare once nothing in this database points at it}';

    protected $description = 'Attach the missing Adobe RGB profile to images on Cloudflare so they show their true colours';

    private const PREFIX = 'srgb/';

    public function handle(CloudflareImages $images): int
    {
        if (! $images->isConfigured()) {
            $this->error('Cloudflare is not configured: set CLOUDFLARE_ACCOUNT_ID, CLOUDFLARE_API_TOKEN and CLOUDFLARE_IMAGES_HASH.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $state = $this->loadState();

        $this->info('Looking for Cloudflare images…');
        $ids = $this->scan();

        $queue = collect($ids)
            ->reject(fn ($id) => str_starts_with($id, self::PREFIX) || isset($state[$id]))
            ->values();

        if ((int) $this->option('limit') > 0) {
            $queue = $queue->take((int) $this->option('limit'));
        }

        $this->line(sprintf('%d images in use, %d still to check.', count($ids), $queue->count()));

        $needing = 0;
        $failures = 0;

        if ($queue->isNotEmpty()) {
            $bar = $this->output->createProgressBar($queue->count());

            foreach ($queue as $id) {
                try {
                    $original = $images->download($id);
                    $tagged = ColorProfile::tagAdobeRgb($original);

                    if ($tagged === $original) {
                        $state[$id] = 'ok';
                    } else {
                        $needing++;

                        if ($execute) {
                            $state[$id] = $this->uploadCopy($images, $tagged, self::PREFIX.$id);
                        }
                    }
                } catch (Throwable $e) {
                    $failures++;
                    $this->newLine();
                    $this->error("  {$id}: {$e->getMessage()}");
                }

                /* Record as we go, so a stopped run picks up where it left off. */
                $this->saveState($state);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        $fixed = array_filter($state, fn ($value) => $value !== 'ok');

        if (! $execute) {
            $this->newLine();
            $this->line("{$needing} of the images checked are missing their Adobe RGB profile.");
            $this->warn('Dry run — nothing was uploaded or changed.');
            $this->line('Run again with --execute to fix them (add --limit=5 for a short trial first).');

            return $failures ? self::FAILURE : self::SUCCESS;
        }

        $rewritten = $this->rewrite($images, $fixed);
        SuitConfiguratorController::forgetCache();

        $this->newLine();
        $this->info(sprintf('%d images fixed in all; %d rows now point at the fixed copies.', count($fixed), $rewritten));

        if ($this->option('delete-old')) {
            $this->deleteOriginals($images, $fixed);
        }

        if ($failures) {
            $this->warn("{$failures} images could not be checked — see above. Run the command again to retry them.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Every distinct image id the database points at, read from delivery
     * addresses written plainly or with escaped slashes inside JSON.
     *
     * @return array<int, string>
     */
    private function scan(): array
    {
        $pattern = $this->addressPattern();
        $ids = [];

        foreach ($this->textColumns() as [$table, $column]) {
            $rows = DB::table($table)->where($column, 'like', '%imagedelivery.net%')->pluck($column);

            foreach ($rows as $value) {
                preg_match_all($pattern, str_replace('\\/', '/', (string) $value), $matches);

                foreach ($matches[1] as $encoded) {
                    $ids[rawurldecode($encoded)] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /* https://imagedelivery.net/<hash>/<id, which may hold slashes>/<variant> */
    private function addressPattern(): string
    {
        $hash = preg_quote((string) config('services.cloudflare.images_hash'), '~');

        return "~https://imagedelivery\\.net/{$hash}/([^\\s\"'<>?#]+)/[^/\\s\"'<>?#]+~";
    }

    private function uploadCopy(CloudflareImages $images, string $bytes, string $id): string
    {
        try {
            $images->upload($bytes, $id);
        } catch (Throwable $e) {
            /* A run stopped between uploading and recording leaves the copy already there. */
            if (! $images->exists($id)) {
                throw $e;
            }
        }

        return $id;
    }

    /**
     * Point every address and stored path at the fixed copy.
     *
     * @param  array<string, string>  $fixed  old id => new id
     */
    private function rewrite(CloudflareImages $images, array $fixed): int
    {
        if ($fixed === []) {
            return 0;
        }

        $replace = [];

        foreach ($fixed as $old => $new) {
            /* In addresses: the id between the hash and the variant… */
            $from = rtrim($images->url($old, '_'), '_');
            $to = rtrim($images->url($new, '_'), '_');
            $replace[$from] = $to;
            $replace[str_replace('/', '\\/', $from)] = str_replace('/', '\\/', $to);

            /* …and as a disk path a form saved, quoted inside JSON. */
            $replace['"'.$old.'"'] = '"'.$new.'"';
            $replace['"'.str_replace('/', '\\/', $old).'"'] = '"'.str_replace('/', '\\/', $new).'"';
        }

        $rewritten = 0;

        foreach ($this->textColumns() as [$table, $column]) {
            if (! Schema::hasColumn($table, 'id')) {
                continue;
            }

            DB::table($table)
                ->where(fn ($q) => $q->where($column, 'like', '%imagedelivery.net%')->orWhereIn($column, array_keys($fixed)))
                ->select(['id', $column])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $column, $replace, $fixed, &$rewritten) {
                    foreach ($rows as $row) {
                        $old = (string) $row->{$column};

                        /* A column holding nothing but a path is the path. */
                        $value = $fixed[$old] ?? strtr($old, $replace);

                        if ($value !== $old) {
                            DB::table($table)->where('id', $row->id)->update([$column => $value]);
                            $rewritten++;
                        }
                    }
                });
        }

        return $rewritten;
    }

    /** @param  array<string, string>  $fixed  old id => new id */
    private function deleteOriginals(CloudflareImages $images, array $fixed): void
    {
        $stillUsed = array_flip($this->scan());
        $deleted = 0;

        foreach (array_keys($fixed) as $old) {
            if (isset($stillUsed[$old])) {
                $this->warn("  Kept {$old}: something still points at it.");

                continue;
            }

            try {
                $images->delete($old);
                $deleted++;
            } catch (Throwable $e) {
                $this->error("  {$old}: {$e->getMessage()}");
            }
        }

        $this->info("Deleted {$deleted} originals from Cloudflare.");
    }

    private function statePath(): string
    {
        return storage_path('app/color-profile-fix.json');
    }

    /** @return array<string, string> id => "ok", or the id of its fixed copy */
    private function loadState(): array
    {
        return is_file($this->statePath())
            ? json_decode(file_get_contents($this->statePath()), true) ?: []
            : [];
    }

    private function saveState(array $state): void
    {
        file_put_contents($this->statePath(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
