<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fabric extends Model
{
    use BustsConfiguratorCache;

    protected $fillable = [
        'sort_order',
        'name',
        'price',
        'image',
        'is_default',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    public function sleeves()
    {
        return $this->hasMany(Sleeve::class)->orderBy('sort_order');
    }

    public function sidePockets()
    {
        return $this->hasMany(SidePocket::class)->orderBy('sort_order');
    }

    public function chestPockets()
    {
        return $this->hasMany(ChestPocket::class)->orderBy('sort_order');
    }

    public function body()
    {
        return $this->hasMany(Body::class)->orderBy('sort_order');
    }

    public function lapels()
    {
        return $this->hasMany(Lapel::class)->orderBy('sort_order');
    }


    public function bodyButtons()
    {
        return $this->hasMany(BodyButton::class)->orderBy('sort_order');
    }

    public function customLinings()
    {
        return $this->hasMany(CustomLining::class)->orderBy('sort_order');
    }

    public function defaultLinings(): HasMany
    {
        return $this->hasMany(DefaultLining::class)->orderBy('sort_order');
    }
}
