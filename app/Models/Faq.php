<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = ['sort_order', 'category', 'question', 'answer', 'is_published'];

    protected $casts = [
        'is_published' => 'boolean',
    ];
}
