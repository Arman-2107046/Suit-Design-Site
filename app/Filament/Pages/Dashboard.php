<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/*
 * Two columns on wide screens: the pipeline and the fabric ranking side by
 * side, with design trends and the customer map each given a whole row.
 * Everything stacks below that.
 */
class Dashboard extends BaseDashboard
{
    public function getColumns(): int | array
    {
        return ['default' => 1, 'xl' => 2];
    }
}
