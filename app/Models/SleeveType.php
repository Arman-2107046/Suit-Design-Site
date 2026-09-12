<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SleeveType extends Model
{
    use BustsConfiguratorCache;

    protected $fillable = [
        'name',
        'code',
        'diagram',
    ];


    public function sleeves(): HasMany
    {
        return $this->hasMany(Sleeve::class);
    }
}
