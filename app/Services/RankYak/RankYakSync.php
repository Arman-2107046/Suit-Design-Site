<?php

namespace App\Services\RankYak;

use App\Models\BlogPost;
use App\Models\RankYakSetting;
use Throwable;

/*
 * The catch-up: pulls every article through the API (so nothing is lost if a
 * webhook was missed) and reports the address of each post that has gone live
 * since. Runs hourly, and from the "Sync now" button.
 */
class RankYakSync
{
    private const MAX_PAGES = 20;   // 2,000 articles; RankYak lists newest first

    /** @return array{imported: int, refreshed: int, unchanged: int, reported: int, failed: list<string>} */
    public function run(): array
    {
        $settings = RankYakSetting::current();
        $tally = ['imported' => 0, 'refreshed' => 0, 'unchanged' => 0, 'reported' => 0, 'failed' => []];

        if (! $settings->enabled || ! $settings->hasApiKey()) {
            return $tally;
        }

        $client = new RankYakClient($settings->api_key);
        $importer = new RankYakImporter($settings, app(\App\Services\Cloudflare\CloudflareImages::class));

        try {
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $result = $client->articles($page);

                foreach ($result['data'] as $article) {
                    try {
                        $outcome = $importer->import($article, onlyIfChanged: true);
                        $tally[match (true) {
                            $outcome['created'] => 'imported',
                            $outcome['changed'] => 'refreshed',
                            default => 'unchanged',
                        }]++;
                    } catch (Throwable $e) {
                        $tally['failed'][] = ($article['title'] ?? 'Article '.($article['id'] ?? '?')).': '.$e->getMessage();
                    }
                }

                if (! $result['next']) {
                    break;
                }
            }
        } catch (Throwable $e) {
            $settings->recordError(RankYakClient::explain($e));
            $tally['failed'][] = RankYakClient::explain($e);

            return $tally;
        }

        $tally['reported'] = $this->reportLivePosts($importer, $tally['failed']);

        $settings->forceFill(['last_synced_at' => now()])->save();
        if ($tally['failed'] === []) {
            $settings->forceFill(['last_error' => null, 'last_error_at' => null])->save();
        }

        return $tally;
    }

    /** Posts that are live now but RankYak has not been told about yet: scheduled ones that just went out, drafts an admin published. */
    private function reportLivePosts(RankYakImporter $importer, array &$failed): int
    {
        $reported = 0;

        BlogPost::query()
            ->where('source', RankYakImporter::SOURCE)
            ->whereNull('external_url_reported_at')
            ->published()
            ->each(function (BlogPost $post) use ($importer, &$reported, &$failed) {
                try {
                    $reported += (int) $importer->reportUrl($post);
                } catch (Throwable $e) {
                    $failed[] = "Reporting “{$post->title}”: ".RankYakClient::explain($e);
                }
            });

        return $reported;
    }
}
