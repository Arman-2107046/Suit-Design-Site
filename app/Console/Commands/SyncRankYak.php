<?php

namespace App\Console\Commands;

use App\Models\RankYakSetting;
use App\Services\RankYak\RankYakSync;
use Illuminate\Console\Command;

class SyncRankYak extends Command
{
    protected $signature = 'rankyak:sync';

    protected $description = 'Import any RankYak articles the webhook missed, and report newly live posts back to RankYak';

    public function handle(RankYakSync $sync): int
    {
        $settings = RankYakSetting::current();

        if (! $settings->enabled || ! $settings->hasApiKey()) {
            $this->components->info('RankYak sync is off (needs the integration switched on and an API key).');

            return self::SUCCESS;
        }

        $tally = $sync->run();

        $this->components->info(sprintf(
            '%d imported, %d refreshed, %d unchanged, %d reported to RankYak.',
            $tally['imported'], $tally['refreshed'], $tally['unchanged'], $tally['reported'],
        ));

        foreach ($tally['failed'] as $failure) {
            $this->components->warn($failure);
        }

        return $tally['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
