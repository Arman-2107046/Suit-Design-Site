@php
    /** @var \App\Models\Activity $activity */
    $activity = $getRecord();
    $name = $activity->admin?->name ?? $activity->admin_name;
    $initials = \App\Models\Admin::initialsFor($name);
@endphp
<div style="display: flex; align-items: center; gap: 10px; padding: 4px 12px;">
    @include('filament.partials.admin-avatar', ['name' => $name, 'initials' => $initials, 'size' => 30])

    <div style="min-width: 0; line-height: 1.25;">
        <div @class(['ct-person-name', 'ct-person-unknown' => ! $name]) style="font-size: 13.5px; white-space: nowrap;">
            {{ $name ?? 'Unknown' }}
        </div>
        @if ($name && ! $activity->admin)
            <div class="ct-person-sub" style="font-size: 11.5px;">Removed</div>
        @endif
    </div>
</div>
