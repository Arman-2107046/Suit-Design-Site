<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyProfile extends Model
{
    protected $fillable = ['user_id', 'name', 'height_cm', 'weight_kg', 'age', 'measurements'];

    protected $casts = [
        'measurements' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The shape the checkout and orders carry around. */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'height_cm' => $this->height_cm,
            'weight_kg' => $this->weight_kg,
            'age' => $this->age,
            'measurements' => $this->measurements,
        ];
    }
}
