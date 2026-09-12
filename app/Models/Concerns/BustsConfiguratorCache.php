<?php

namespace App\Models\Concerns;

use App\Http\Controllers\Api\SuitConfiguratorController;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Busts Configurator Cache
|--------------------------------------------------------------------------
|
| The /api/configurator payload is cached (see SuitConfiguratorController).
| Any model that feeds that payload uses this trait so that creating,
| updating or deleting a row through Eloquent (Filament forms, importers,
| updateOrCreate, ...) invalidates the cached payload immediately.
|
*/
trait BustsConfiguratorCache
{
    public static function bootBustsConfiguratorCache(): void
    {
        $bust = static fn () => SuitConfiguratorController::forgetCache();

        static::saved($bust);
        static::deleted($bust);
    }
}
