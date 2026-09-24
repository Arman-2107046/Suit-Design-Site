<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use App\Models\Concerns\OrderedByType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodyType extends Model
{
    use BustsConfiguratorCache;
    use OrderedByType;

    protected $fillable = [
        'name',
        'code',
        'diagram',
    ];

    public function bodies(): HasMany
    {
        return $this->hasMany(Body::class)->orderBy('sort_order');
    }
    public function bodyButtons(): HasMany
    {
        return $this->orderedByType($this->hasMany(BodyButton::class), [
            ButtonImage::class => 'button_image_id',
        ]);
    }

    public function defaultLinings(): HasMany
    {
        return $this->orderedByType($this->hasMany(DefaultLining::class), [
            LiningType::class => 'lining_type_id',
        ]);
    }
}
