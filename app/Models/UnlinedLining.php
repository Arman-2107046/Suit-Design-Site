<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * A fabric's jacket with no lining, plain (UL_) or with the plate (ULP_). One of
 * each kind per fabric and the same for every jacket style, so, like a custom
 * lining, it names no body.
 */
class UnlinedLining extends Model
{
    use BustsConfiguratorCache;

    public const UNLINED = 'unlined';

    public const PLATE = 'plate';

    protected $fillable = [
        'fabric_id',
        'kind',
        'lining_type_id',
        'image',
        'layer_index',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    public function liningType(): BelongsTo
    {
        return $this->belongsTo(LiningType::class);
    }
}
