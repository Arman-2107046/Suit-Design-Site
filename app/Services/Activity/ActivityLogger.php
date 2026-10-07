<?php

namespace App\Services\Activity;

use App\Models\Activity;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/*
 * Writes the activity log. Only what an administrator does in the admin is
 * recorded: a customer checking out writes orders too, and none of that is
 * the team's activity.
 */
class ActivityLogger
{
    /** Never worth a line of their own in a diff. */
    private const IGNORED = ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'last_login_at'];

    /** Kept out of the log entirely, whatever a model says about hiding them. */
    private const SECRET = ['password', 'remember_token', 'token', 'secret', 'api_token'];

    private const MAX_LENGTH = 500;

    private static int $muted = 0;

    /**
     * Run something without logging each record it writes, typically because
     * the caller logs one summary for the lot.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function quietly(callable $callback): mixed
    {
        self::$muted++;

        try {
            return $callback();
        } finally {
            self::$muted--;
        }
    }

    /**
     * The administrator behind the current request, if this is an admin
     * request at all. The admin guard is only the default once the admin's
     * own middleware has authenticated the request, so an admin who is also
     * shopping in the same browser is not logged for it.
     */
    public static function actor(): ?Admin
    {
        if (Auth::getDefaultDriver() !== 'admin') {
            return null;
        }

        $admin = Auth::guard('admin')->user();

        return $admin instanceof Admin ? $admin : null;
    }

    /**
     * @param  Model|class-string<Model>|null  $subject  a record, or a model class when the entry is about a kind of record (a reorder, say)
     */
    public static function log(string $event, Model|string|null $subject = null, array $properties = [], ?Admin $admin = null, ?string $label = null, ?string $actorName = null): ?Activity
    {
        $admin ??= self::actor();
        $record = $subject instanceof Model ? $subject : null;

        try {
            return Activity::create([
                'admin_id' => $admin?->getKey(),
                /* An integration acting on its own (RankYak) is named; an admin who set it off is named instead */
                'admin_name' => $admin?->name ?? $actorName,
                'event' => $event,
                'subject_type' => is_string($subject) ? (new $subject)->getMorphClass() : $record?->getMorphClass(),
                'subject_id' => $record?->getKey(),
                'subject_label' => $label ?? ($record ? self::labelFor($record) : null),
                'properties' => $properties ?: null,
                'ip' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 250, ''),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            /* Losing a log line is bad; failing the admin's save because of it is worse. */
            report($e);

            return null;
        }
    }

    /**
     * A batch reaches the server in chunks of 50. Chunks that follow on within
     * a few minutes join the admin's current batch entry, so a 4,000-file
     * upload reads as one line rather than eighty.
     */
    public static function logBulkUpload(array $summary): void
    {
        $admin = self::actor();

        $current = Activity::query()
            ->where('event', 'bulk_upload')
            ->where('admin_id', $admin?->getKey())
            ->where('created_at', '>=', now()->subMinutes(5))
            ->latest('id')
            ->first();

        if (! $current) {
            self::log('bulk_upload', properties: [
                'filed' => $summary['filed'],
                'failed' => $summary['failed'],
                'breakdown' => $summary['breakdown'],
                'last_chunk_at' => now()->toIso8601String(),
            ], admin: $admin);

            return;
        }

        $properties = $current->properties;
        $properties['filed'] += $summary['filed'];
        $properties['failed'] += $summary['failed'];
        foreach ($summary['breakdown'] as $destination => $count) {
            $properties['breakdown'][$destination] = ($properties['breakdown'][$destination] ?? 0) + $count;
        }
        arsort($properties['breakdown']);
        $properties['last_chunk_at'] = now()->toIso8601String();

        /* Stamped with the latest chunk: the line reads as when the batch finished, and the window runs on from there. */
        $current->forceFill(['properties' => $properties, 'created_at' => now()])->save();
    }

    /** Logs a model event, when an admin caused it. */
    public static function record(string $event, Model $model): void
    {
        if (self::$muted > 0 || $model instanceof Activity || ! ($admin = self::actor())) {
            return;
        }

        $properties = match ($event) {
            'updated' => self::diff($model),
            default => ['attributes' => self::clean($model, $model->getAttributes())],
        };

        if ($event === 'updated' && $properties['new'] === []) {
            return;   // only timestamps or secrets moved
        }

        self::log($event, $model, $properties, $admin);
    }

    private static function diff(Model $model): array
    {
        $new = self::clean($model, $model->getChanges());

        $raw = array_map(
            fn (string $key) => $model->getRawOriginal($key),
            array_combine(array_keys($new), array_keys($new))
        );

        /* 'old' and 'new' are for reading, shortened. 'restore' keeps the previous values whole,
           exactly as stored, so the edit can be undone later (secrets are never kept). */
        return ['old' => self::clean($model, $raw), 'new' => $new, 'restore' => self::restorable($model, $raw)];
    }

    private static function clean(Model $model, array $attributes): array
    {
        $hidden = $model->getHidden();
        $clean = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, self::IGNORED, true)) {
                continue;
            }

            $clean[$key] = in_array($key, self::SECRET, true) || in_array($key, $hidden, true)
                ? '••••••'
                : self::value($value);
        }

        return $clean;
    }

    /** Previous values in full, as the database holds them, minus anything secret. */
    private static function restorable(Model $model, array $raw): array
    {
        $hidden = $model->getHidden();

        return array_filter(
            $raw,
            fn (string $key) => ! in_array($key, self::SECRET, true) && ! in_array($key, $hidden, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** A value as the log shows it: the same shortening the diff uses, so values can be compared. */
    public static function display(mixed $value): mixed
    {
        return self::value($value);
    }

    private static function value(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && mb_strlen($value) > self::MAX_LENGTH) {
            return mb_substr($value, 0, self::MAX_LENGTH).'…';
        }

        return is_scalar($value) || $value === null ? $value : Str::limit(json_encode($value), self::MAX_LENGTH);
    }

    public static function labelFor(Model $model): string
    {
        foreach (['name', 'title', 'number', 'subject', 'email', 'code', 'slug'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return Str::limit($value, 120);
            }
        }

        return '#'.$model->getKey();
    }
}
