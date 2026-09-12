<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidePocket extends Model
{
    use BustsConfiguratorCache;

    protected $fillable = [
        'fabric_id',
        'side_pocket_type_id',
        'image',
        'layer_index',
        'is_default',
        'status',
    ];

    protected $casts = [
        'layer_index' => 'integer',
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    public function sidePocketType(): BelongsTo
    {
        return $this->belongsTo(SidepocketType::class);
    }
}
