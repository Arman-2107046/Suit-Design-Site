<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefaultLining extends Model
{
    use BustsConfiguratorCache;

    protected $fillable = [
        'fabric_id',
        'body_type_id',
        'lining_type_id',
        'image',
        'layer_index',
        'status',
    ];

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    public function bodyType(): BelongsTo
    {
        return $this->belongsTo(BodyType::class);
    }

    public function liningType(): BelongsTo
    {
        return $this->belongsTo(LiningType::class);
    }
}
