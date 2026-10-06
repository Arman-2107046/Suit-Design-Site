<?php

namespace App\Models\Concerns;

/*
 * For option styles where one is the starting choice on every fabric — the
 * default shoulder, lapel style, lapel width. Marking one as default unmarks
 * whichever was default before, so there is never more than one.
 *
 * The model needs an `is_default` boolean column.
 */
trait HasSingleDefault
{
    public static function bootHasSingleDefault(): void
    {
        static::saving(function (self $model) {
            if ($model->is_default && $model->isDirty('is_default')) {
                static::query()
                    ->whereKeyNot($model->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /** The id of the one marked default, if any. */
    public static function defaultId(): ?int
    {
        $id = static::query()->where('is_default', true)->value('id');

        return $id === null ? null : (int) $id;
    }
}
