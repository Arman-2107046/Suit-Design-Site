<?php

namespace App\Services\ImageHealth;

use App\Models\ImageHealthRun;
use App\Models\ImageIssue;
use App\Services\Cloudflare\CloudflareImages;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/*
 * Tests every picture the site uses and records the ones that do not load.
 *
 * Pictures on our own Cloudflare Images account are checked against the
 * account's list of images, one request for thousands, rather than fetched
 * one by one. Anything else (old Cloudinary addresses, other hosts) is asked
 * for directly, a batch at a time.
 */
class ImageHealthCheck
{
    private const BATCH = 20;

    public function __construct(private readonly CloudflareImages $cloudflare) {}

    public function run(): ImageHealthRun
    {
        /* Edit links are built from the admin panel's routes, also when run from the scheduler */
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $run = ImageHealthRun::create(['started_at' => now()]);

        try {
            $entries = [...$this->recordEntries(), ...$this->settingEntries()];
            $cloudflareIds = $this->cloudflareIds();

            $issues = [];
            $toProbe = [];

            foreach ($entries as $entry) {
                if (blank($entry['url'])) {
                    if ($entry['required']) {
                        $issues[] = $entry + ['reason' => 'missing', 'http_status' => null];
                    }

                    continue;
                }

                $id = $cloudflareIds === null ? null : $this->cloudflare->idFromUrl($entry['url']);

                if ($id !== null) {
                    if (! isset($cloudflareIds[$id])) {
                        $issues[] = $entry + ['reason' => 'not_on_cloudflare', 'http_status' => null];
                    }

                    continue;
                }

                $toProbe[$entry['url']][] = $entry;
            }

            foreach ($this->probe(array_keys($toProbe)) as $url => [$reason, $status]) {
                foreach ($toProbe[$url] as $entry) {
                    $issues[] = $entry + ['reason' => $reason, 'http_status' => $status];
                }
            }

            $this->save($run, $issues);

            $run->update([
                'checked_count' => count($entries),
                'broken_count' => count($issues),
                'cloudflare_checked' => $cloudflareIds !== null,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
            $run->update(['error' => mb_substr($e->getMessage(), 0, 490), 'finished_at' => now()]);
        }

        return $run->fresh();
    }

    /** @return list<array<string, mixed>> */
    private function recordEntries(): array
    {
        $entries = [];

        foreach (ImageSources::records() as $source) {
            /** @var class-string<Model> $model */
            $model = $source['model'];
            $table = (new $model)->getTable();

            if (! Schema::hasTable($table)) {
                continue;
            }

            $hasStatus = Schema::hasColumn($table, 'status');
            $columns = array_filter([(new $model)->getKeyName(), $source['field'], $hasStatus ? 'status' : null]);

            foreach (DB::table($table)->select($columns)->orderBy('id')->lazy(1000) as $row) {
                $status = $hasStatus ? $row->status : null;
                $entries[] = [
                    'source' => $source['group'],
                    'model_type' => $model,
                    'model_id' => $row->id,
                    'field' => $source['field'],
                    'url' => $row->{$source['field']},
                    'required' => $source['required'],
                    /* status is 'published'/'draft' on posts, true/false elsewhere */
                    'hidden' => $hasStatus && ($status === 0 || $status === '0' || $status === false || $status === 'draft'),
                ];
            }
        }

        return $entries;
    }

    /** @return list<array<string, mixed>> */
    private function settingEntries(): array
    {
        $entries = [];

        foreach (ImageSources::settings() as $model => $setting) {
            $row = $model::query()->first();

            if (! $row) {
                continue;
            }

            foreach ($setting['fields'] as $field => $label) {
                $entries[] = $this->settingEntry($setting, $model, $row, $field, $label, $row->getAttribute($field));
            }

            foreach ($setting['lists'] ?? [] as $field => $label) {
                foreach (array_values((array) $row->getAttribute($field)) as $i => $item) {
                    $entries[] = $this->settingEntry($setting, $model, $row, $field, $label.' '.($i + 1), is_array($item) ? ($item['url'] ?? null) : $item);
                }
            }
        }

        return $entries;
    }

    private function settingEntry(array $setting, string $model, Model $row, string $field, string $label, ?string $url): array
    {
        return [
            'source' => $setting['group'],
            'model_type' => $model,
            'model_id' => $row->getKey(),
            'field' => $field,
            'url' => $url,
            'required' => false,
            'hidden' => false,
            'label' => $label,
            'edit_url' => $setting['page']::getUrl(),
        ];
    }

    /** @return array<string, true>|null null when Cloudflare cannot be asked, so its pictures are probed like any others */
    private function cloudflareIds(): ?array
    {
        if (! $this->cloudflare->isConfigured()) {
            return null;
        }

        try {
            return $this->cloudflare->allIds();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Ask each address for its picture, a batch at a time.
     *
     * @param  list<string>  $urls
     * @return array<string, array{0: string, 1: ?int}> only the ones that failed: url => [reason, status]
     */
    private function probe(array $urls): array
    {
        $failed = [];

        foreach (array_chunk($urls, self::BATCH) as $batch) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $url) => $pool->as($url)->withHeaders(['Range' => 'bytes=0-0'])->connectTimeout(5)->timeout(15)->get($url),
                $batch,
            ));

            foreach ($batch as $url) {
                $response = $responses[$url] ?? null;

                if ($response instanceof Response && $response->successful()) {
                    continue;
                }

                $status = $response instanceof Response ? $response->status() : null;

                $failed[$url] = [match (true) {
                    $status === null, $response instanceof ConnectionException, $response instanceof Throwable => 'unreachable',
                    in_array($status, [401, 403], true) => 'refused',
                    in_array($status, [404, 410], true) => 'not_found',
                    $status >= 500 => 'server_error',
                    default => 'other',
                }, $status];
            }
        }

        return $failed;
    }

    /** Replace the issue list with this run's, labelled and linked for the admin. */
    private function save(ImageHealthRun $run, array $issues): void
    {
        $labels = [];
        $editUrls = [];

        foreach (collect($issues)->whereNull('label')->groupBy('model_type') as $model => $group) {
            $source = collect(ImageSources::records())->firstWhere('model', $model);

            $model::query()->with($source['with'] ?? [])->whereKey($group->pluck('model_id')->unique()->all())->get()
                ->each(function (Model $record) use ($model, $source, &$labels, &$editUrls) {
                    $labels[$model][$record->getKey()] = ($source['label'])($record);
                    $editUrls[$model][$record->getKey()] = $this->editUrl($record);
                });
        }

        DB::transaction(function () use ($run, $issues, $labels, $editUrls) {
            ImageIssue::query()->delete();

            foreach (array_chunk($issues, 500) as $chunk) {
                ImageIssue::insert(array_map(fn (array $issue) => [
                    'image_health_run_id' => $run->id,
                    'source' => $issue['source'],
                    'model_type' => $issue['model_type'],
                    'model_id' => $issue['model_id'],
                    'field' => $issue['field'],
                    'label' => mb_substr($issue['label'] ?? $labels[$issue['model_type']][$issue['model_id']] ?? '#'.$issue['model_id'], 0, 250),
                    'url' => $issue['url'],
                    'reason' => $issue['reason'],
                    'http_status' => $issue['http_status'],
                    'hidden' => $issue['hidden'],
                    'edit_url' => $issue['edit_url'] ?? $editUrls[$issue['model_type']][$issue['model_id']] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $chunk));
            }
        });
    }

    private function editUrl(Model $record): ?string
    {
        $resource = Filament::getModelResource($record);

        if (! $resource) {
            return null;
        }

        foreach (['edit', 'view'] as $page) {
            if ($resource::hasPage($page)) {
                return $resource::getUrl($page, ['record' => $record]);
            }
        }

        return null;
    }
}
