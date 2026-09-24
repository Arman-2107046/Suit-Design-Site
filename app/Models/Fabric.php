<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Fabric extends Model
{
    use BustsConfiguratorCache;
    use Concerns\OrderedByType;

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
        return $this->orderedByType($this->hasMany(Sleeve::class), [
            SleeveType::class => 'sleeve_type_id',
        ]);
    }

    public function sidePockets()
    {
        return $this->orderedByType($this->hasMany(SidePocket::class), [
            SidepocketType::class => 'side_pocket_type_id',
        ]);
    }

    public function chestPockets()
    {
        return $this->orderedByType($this->hasMany(ChestPocket::class), [
            ChestPocketType::class => 'chest_pocket_type_id',
        ]);
    }

    public function body()
    {
        return $this->orderedByType($this->hasMany(Body::class), [
            BodyType::class => 'body_type_id',
        ]);
    }

    public function lapels()
    {
        return $this->orderedByType($this->hasMany(Lapel::class), [
            LapelCategory::class => 'lapel_category_id',
            LapelSubCategory::class => 'lapel_subcategory_id',
        ]);
    }


    public function bodyButtons()
    {
        return $this->orderedByType($this->hasMany(BodyButton::class), [
            ButtonImage::class => 'button_image_id',
        ]);
    }

    public function customLinings()
    {
        return $this->orderedByType($this->hasMany(CustomLining::class), [
            LiningType::class => 'lining_type_id',
            CustomLiningFabric::class => 'custom_lining_fabric_id',
        ]);
    }

    public function defaultLinings(): HasMany
    {
        return $this->orderedByType($this->hasMany(DefaultLining::class), [
            LiningType::class => 'lining_type_id',
        ]);
    }
}
