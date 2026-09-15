<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['fabric_id', 'fabric_name', 'fabric_image', 'price', 'quantity', 'design', 'summary'];

    protected $casts = [
        'price' => 'decimal:2',
        'design' => 'array',
        'summary' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }
}
