@php
    /** @var \App\Models\Admin $admin */
    $admin = $getRecord();
    $isYou = $admin->is(\Filament\Facades\Filament::auth()->user());
@endphp
<div style="display: flex; align-items: center; gap: 12px; padding: 4px 12px;">
    @include('filament.partials.admin-avatar', ['name' => $admin->name, 'initials' => $admin->initials(), 'size' => 38])

    <div style="min-width: 0; line-height: 1.3;">
        <div style="display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; color: #0f172a;">
            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $admin->name }}</span>
            @if ($isYou)
                <span style="padding: 1px 7px; border-radius: 9999px; background: #eef2ff; color: #4338ca; font-size: 10.5px; font-weight: 600;">You</span>
            @endif
        </div>
        <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12.5px; color: #64748b;">{{ $admin->email }}</div>
    </div>
</div>
