<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabricImage extends Model
{
    use BustsConfiguratorCache;

    public const KINDS = ['preview' => 'Preview', 'real_life' => 'Real life'];

    public const MAX_PER_KIND = 10;

    protected $fillable = ['fabric_id', 'kind', 'url', 'sort_order'];

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }
}
