<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogComment extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'hidden' => 'Hidden'];

    protected $fillable = ['blog_post_id', 'user_id', 'name', 'email', 'body', 'status'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
