<?php

namespace App\Console\Commands;

use App\Models\ImageHealthRun;
use App\Services\AdminNotifier;
use App\Services\ImageHealth\ImageHealthCheck;
use Illuminate\Console\Command;

class CheckImages extends Command
{
    protected $signature = 'images:check';

    protected $description = 'Test every picture the site uses and list the ones that do not load (Content → Image health)';

    public function handle(ImageHealthCheck $check): int
    {
        $previous = ImageHealthRun::latestFinished();
        $run = $check->run();

        if ($run->error) {
            $this->components->error('The check stopped: '.$run->error);

            return self::FAILURE;
        }

        $this->components->info(sprintf('Checked %s pictures in %ss: %d broken.', number_format($run->checked_count), $run->seconds(), $run->broken_count));

        /* Only news reaches the bell: more broken than last time */
        if ($run->broken_count > ($previous?->broken_count ?? 0)) {
            AdminNotifier::brokenImages($run->broken_count, $run->broken_count - ($previous?->broken_count ?? 0));
        }

        return self::SUCCESS;
    }
}
