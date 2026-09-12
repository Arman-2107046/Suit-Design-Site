<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LapelCategory extends Model
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
     * Sub categories under this lapel category
     */
    public function subCategories(): HasMany
    {
        return $this->hasMany(
            LapelSubCategory::class
        );
    }

    /**
     * Lapels using this category
     */
    public function lapel(): HasMany
    {
        return $this->hasMany(Lapel::class);
    }
}
