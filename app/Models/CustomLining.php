<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use App\Models\Concerns\OrderedByType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * A custom lining belongs to no fabric: any lining can go inside any suit,
 * so it is keyed by lining type and lining fabric and offered on all of them.
 */
class CustomLining extends Model
{
    use BustsConfiguratorCache;
    use OrderedByType;

    protected $fillable = [
        'custom_lining_fabric_id',
        'lining_type_id',
        'image',
        'layer_index',
        'is_default',
        'status',
    ];

    public function customLiningFabric(): BelongsTo
    {
        return $this->belongsTo(CustomLiningFabric::class);
    }

    public function liningType(): BelongsTo
    {
        return $this->belongsTo(LiningType::class);
    }

    /**
     * The order the admin dragged, since these are no longer reached through
     * a fabric's relation.
     */
    public function scopeInDragOrder(Builder $query): Builder
    {
        return $this->orderedByType($query, [
            LiningType::class => 'lining_type_id',
            CustomLiningFabric::class => 'custom_lining_fabric_id',
        ]);
    }
}
