<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use App\Models\Concerns\OrderedByType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Body extends Model
{
    use BustsConfiguratorCache;
    use OrderedByType;

    protected $fillable = [
        'fabric_id',
        'body_type_id',
        'image',
        'layer_index',
        'is_default',
        'status',
    ];

    protected $casts = [
        'layer_index' => 'integer',
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    public function bodyType(): BelongsTo
    {
        return $this->belongsTo(BodyType::class);
    }

    public function lapels(): HasMany
    {
        return $this->orderedByType($this->hasMany(Lapel::class), [
            LapelCategory::class => 'lapel_category_id',
            LapelSubCategory::class => 'lapel_subcategory_id',
        ]);
    }

    public function bodyButtons(): HasMany
    {
        return $this->orderedByType($this->hasMany(BodyButton::class), [
            ButtonImage::class => 'button_image_id',
        ]);
    }

}
