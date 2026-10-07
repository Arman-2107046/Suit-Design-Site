<?php

namespace App\Services\Activity;

use App\Models\Activity;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/*
 * Undo an edit from the activity log: put the fields it changed back to what
 * they were before. The restore is itself logged (as "restored", pointing at
 * the entry it undid), so it can be undone in turn.
 *
 * Entries keep the previous values whole under properties.restore. Older
 * entries only have the shortened copy in properties.old: a field cut short
 * there cannot be put back faithfully, so it is skipped rather than guessed.
 */
class ActivityRestorer
{
    private const MASKED = '••••••';

    /**
     * What a restore would do, for the confirmation screen and the button.
     *
     * @return array{
     *     possible: bool,
     *     reason: ?string,
     *     record: ?Model,
     *     fields: list<array{field: string, label: string, now: mixed, back_to: mixed, changed_since: bool}>,
     *     skipped: list<array{field: string, label: string, why: string}>,
     * }
     */
    public function plan(Activity $entry): array
    {
        $plan = ['possible' => false, 'reason' => null, 'record' => null, 'fields' => [], 'skipped' => []];

        if (! in_array($entry->event, ['updated', 'restored'], true)) {
            return ['reason' => 'Only edits can be restored.'] + $plan;
        }

        $record = $this->record($entry);
        if (! $record) {
            return ['reason' => 'This record has since been deleted.'] + $plan;
        }

        $properties = $entry->properties ?? [];
        $full = $properties['restore'] ?? null;
        $table = $record->getTable();

        foreach ($properties['new'] ?? [] as $field => $after) {
            $label = Str::headline($field);
            $hasFull = is_array($full) && array_key_exists($field, $full);
            $before = $hasFull ? $full[$field] : ($properties['old'][$field] ?? null);

            $why = match (true) {
                ! Schema::hasColumn($table, $field) => 'The field no longer exists.',
                ($properties['old'][$field] ?? null) === self::MASKED || $after === self::MASKED => 'Private fields such as passwords are never kept in the log.',
                ! $hasFull && $this->wasShortened($properties['old'][$field] ?? null) => 'Too long to have been kept in full by this older entry.',
                default => null,
            };

            if ($why) {
                $plan['skipped'][] = compact('field', 'label', 'why');

                continue;
            }

            $now = $record->getRawOriginal($field);

            $plan['fields'][] = [
                'field' => $field,
                'label' => $label,
                'now' => ActivityLogger::display($now),
                'back_to' => ActivityLogger::display($before),
                'raw' => $before,
                /* Someone has edited this field again since; restoring overwrites that too */
                'changed_since' => ActivityLogger::display($now) != $after,
                'same' => (string) ActivityLogger::display($now) === (string) ActivityLogger::display($before),
            ];
        }

        $toChange = array_filter($plan['fields'], fn (array $f) => ! $f['same']);

        $plan['record'] = $record;
        $plan['fields'] = array_values($toChange);
        $plan['possible'] = $toChange !== [];
        $plan['reason'] = $plan['possible'] ? null : ($plan['skipped'] ? 'Nothing here can be restored.' : 'It already has these values.');

        return $plan;
    }

    /** Put the values back, as the admin doing it, and log the restore. */
    public function restore(Activity $entry, Admin $admin): Activity
    {
        $plan = $this->plan($entry);

        if (! $plan['possible']) {
            throw new RuntimeException($plan['reason'] ?? 'This cannot be restored.');
        }

        /** @var Model $record */
        $record = $plan['record'];

        return DB::transaction(function () use ($record, $plan, $entry, $admin) {
            /* The kept values are exactly what the database held, so they go back the same way,
               past the model's casts: through a cast, JSON would be encoded twice and an encrypted
               value encrypted again. */
            $before = [];
            $attributes = $record->getAttributes();
            foreach ($plan['fields'] as $field) {
                $before[$field['field']] = $record->getRawOriginal($field['field']);
                $attributes[$field['field']] = $field['raw'];
            }
            $record->setRawAttributes($attributes);

            /* One "restored" entry instead of the usual "updated" one */
            ActivityLogger::quietly(fn () => $record->save());

            $changes = $record->getChanges();
            $fields = array_intersect_key($before, $changes);

            return ActivityLogger::log('restored', $record, [
                'old' => array_map([ActivityLogger::class, 'display'], $fields),
                'new' => array_map(fn ($key) => ActivityLogger::display($record->getRawOriginal($key)), array_combine(array_keys($fields), array_keys($fields))),
                'restore' => $fields,
                'restored_from' => $entry->id,
            ], $admin);
        });
    }

    /** The "restored" entry that undid this one, if any. */
    public function restoredBy(Activity $entry): ?Activity
    {
        return Activity::query()->with('admin')->where('event', 'restored')->where('properties->restored_from', $entry->id)->latest('id')->first();
    }

    private function record(Activity $entry): ?Model
    {
        $class = $entry->subject_type;

        if (! $class || ! class_exists($class) || ! $entry->subject_id) {
            return null;
        }

        return $class::query()->find($entry->subject_id);
    }

    /** ActivityLogger cuts long values to 500 characters and adds an ellipsis. */
    private function wasShortened(mixed $value): bool
    {
        return is_string($value) && mb_strlen($value) === 501 && str_ends_with($value, '…');
    }

}
