{{--
    The Grid / List switch at the start of the table toolbar on lists with
    pictures (App\Filament\Support\ImageViews). Rendered inside the list page,
    so wire:click reaches the page's switchImageView().
--}}
<div class="ct-view-switch" role="group" aria-label="Show as">
    @foreach (['grid' => ['Grid', 'heroicon-m-squares-2x2'], 'list' => ['List', 'heroicon-m-list-bullet']] as $option => [$label, $icon])
        <button
            type="button"
            wire:click="switchImageView('{{ $option }}')"
            wire:loading.attr="disabled"
            wire:target="switchImageView"
            @class(['ct-view-switch-btn', 'is-active' => $view === $option])
            aria-pressed="{{ $view === $option ? 'true' : 'false' }}"
            title="Show as {{ strtolower($label) }}"
        >
            <x-filament::icon :icon="$icon" class="ct-view-switch-icon" />
            <span>{{ $label }}</span>
        </button>
    @endforeach
</div>

