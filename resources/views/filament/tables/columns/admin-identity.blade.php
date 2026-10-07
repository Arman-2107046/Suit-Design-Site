@php
    /** @var \App\Models\Admin $admin */
    $admin = $getRecord();
    $isYou = $admin->is(\Filament\Facades\Filament::auth()->user());
@endphp
<div style="display: flex; align-items: center; gap: 12px; padding: 4px 12px;">
    @include('filament.partials.admin-avatar', ['name' => $admin->name, 'initials' => $admin->initials(), 'size' => 38])

    <div style="min-width: 0; line-height: 1.3;">
        <div class="ct-person-name">
            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $admin->name }}</span>
            @if ($isYou)
                <span class="ct-person-you">You</span>
            @endif
        </div>
        <div class="ct-person-sub">{{ $admin->email }}</div>
    </div>
</div>
