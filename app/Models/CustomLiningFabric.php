<?php

namespace App\Models;

use App\Models\Concerns\BustsConfiguratorCache;
use Illuminate\Database\Eloquent\Model;

class CustomLiningFabric extends Model
{
    use BustsConfiguratorCache;

    protected $fillable = [
        'name',
        'image',
        'status',
    ];

    public function customLinings()
    {
        return $this->hasMany(CustomLining::class);
    }
}
