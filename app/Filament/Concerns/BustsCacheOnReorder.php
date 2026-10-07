<?php

namespace App\Filament\Concerns;

use App\Http\Controllers\Api\SuitConfiguratorController;
use App\Services\Activity\ActivityLogger;

/*
 * Filament writes drag-reorder positions with a raw query, which skips the
 * model events the configurator cache and the activity log rely on — so bust
 * the cache and log the reorder explicitly.
 */
trait BustsCacheOnReorder
{
    public function reorderTable(array $order, int | string | null $draggedRecordKey = null): void
    {
        parent::reorderTable($order, $draggedRecordKey);

        SuitConfiguratorController::forgetCache();

        ActivityLogger::log('reordered', $this->getTable()->getModel(), ['count' => count($order)]);
    }
}
