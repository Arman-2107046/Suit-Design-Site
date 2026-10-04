<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\Schema;

/*
 * Image addresses turn up in plain columns, in JSON and inside HTML, so the
 * media commands look through every text column of every content table.
 */
trait ScansContentColumns
{
    /** @return array<int, array{0: string, 1: string}> */
    private function textColumns(): array
    {
        /* Tables that hold framework state rather than content. */
        $skip = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'];

        return collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn ($table) => in_array($table, $skip, true))
            ->flatMap(fn ($table) => collect(Schema::getColumns($table))
                ->filter(fn ($column) => preg_match('/char|text|json/i', $column['type_name']))
                ->map(fn ($column) => [$table, $column['name']]))
            ->values()
            ->all();
    }
}
