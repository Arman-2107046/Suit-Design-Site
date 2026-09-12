<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LapelSubCategory extends Model
{
    use BustsConfiguratorCache;

    protected $fillable = [
        'name',
        'diagram',
        'status',
        'is_default',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Lapels using this sub category
     */
    public function lapels(): HasMany
    {
        return $this->hasMany(Lapel::class);
    }
}
