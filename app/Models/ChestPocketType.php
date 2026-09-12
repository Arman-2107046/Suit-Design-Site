<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChestPocketType extends Model
{
    use BustsConfiguratorCache;

    protected $fillable = [
        'name',
        'code',
        'diagram',
    ];

    public function chestPockets(): HasMany
    {
        return $this->hasMany(ChestPocket::class);
    }
}
