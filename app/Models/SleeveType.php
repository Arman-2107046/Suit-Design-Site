<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use App\Models\Concerns\HasSingleDefault;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SleeveType extends Model
{
    use BustsConfiguratorCache;
    use HasSingleDefault;

    protected $fillable = [
        'name',
        'code',
        'diagram',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function sleeves(): HasMany
    {
        return $this->hasMany(Sleeve::class);
    }
}
