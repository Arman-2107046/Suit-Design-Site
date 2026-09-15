<?php

namespace App\Filament\Concerns;

use App\Http\Controllers\Api\SuitConfiguratorController;

/*
 * Filament writes drag-reorder positions with a raw query, which skips the
 * model events the configurator cache relies on — so bust it explicitly.
 */
trait BustsCacheOnReorder
{
    public function reorderTable(array $order, int | string | null $draggedRecordKey = null): void
    {
        parent::reorderTable($order, $draggedRecordKey);

        SuitConfiguratorController::forgetCache();
    }
}
