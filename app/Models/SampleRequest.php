<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SampleRequest extends Model
{
    public const STATUSES = ['requested' => 'Requested', 'sent' => 'Sent', 'cancelled' => 'Cancelled'];

    protected $fillable = ['user_id', 'email', 'fabrics', 'shipping', 'notes', 'status', 'sent_at'];

    protected $casts = [
        'fabrics' => 'array',
        'shipping' => 'array',
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (SampleRequest $request) {
            if ($request->isDirty('status') && $request->status === 'sent') {
                $request->sent_at ??= now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
