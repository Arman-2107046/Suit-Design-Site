<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Fabric extends Model
{
    use BustsConfiguratorCache;

    protected $fillable = [
        'sort_order',
        'name',
        'price',
        'image',
        'is_default',
        'is_new',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_default' => 'boolean',
        'is_new' => 'boolean',
        'status' => 'boolean',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(FabricImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function previewImages(): HasMany
    {
        return $this->images()->where('kind', 'preview');
    }

    public function realLifeImages(): HasMany
    {
        return $this->images()->where('kind', 'real_life');
    }

    public function infos(): HasMany
    {
        return $this->hasMany(FabricInfo::class)->latest('updated_at');
    }

    /** The card shown behind "more info": the most recently edited info record, if any. */
    public function latestInfo(): HasOne
    {
        return $this->hasOne(FabricInfo::class)->latestOfMany('updated_at');
    }

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
