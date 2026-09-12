<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SidePocketType extends Model
{
    use BustsConfiguratorCache;

    protected $fillable = [
        'name',
        'code',
        'diagram',
    ];

    public function sidePockets(): HasMany
    {
        return $this->hasMany(SidePocket::class);
    }
}
