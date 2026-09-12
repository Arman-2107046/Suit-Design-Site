<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodyType extends Model
{
    use BustsConfiguratorCache;

    protected $fillable = [
        'name',
        'code',
        'diagram',
    ];

    public function bodies(): HasMany
    {
        return $this->hasMany(Body::class);
    }
    public function bodyButtons(): HasMany
    {
        return $this->hasMany(BodyButton::class);
    }

    public function defaultLinings(): HasMany
    {
        return $this->hasMany(DefaultLining::class);
    }
}
