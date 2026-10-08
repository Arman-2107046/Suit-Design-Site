<?php

namespace App\Filament\Concerns;

use App\Filament\Support\ImageViews;

/*
 * For list pages whose records have pictures: the Grid / List switch in the
 * table toolbar. The choice is kept per list for the rest of the session.
 * The table itself is shaped by App\Filament\Support\ImageViews.
 */
trait HasImageViews
{
    public ?string $imageView = null;

    public function mountHasImageViews(): void
    {
        $this->imageView ??= session($this->imageViewSessionKey());
    }

    public function switchImageView(string $view): void
    {
        if (! in_array($view, [ImageViews::GRID, ImageViews::LIST], true) || $view === $this->imageView) {
            return;
        }

        $this->imageView = $view;
        session()->put($this->imageViewSessionKey(), $view);

        /* The table was built for the old view at the start of this request: build it again */
        $this->bootedInteractsWithTable();
        $this->flushCachedTableRecords();
    }

    /** The view being shown, or the list's own default until the admin picks one. */
    public function currentImageView(string $default): string
    {
        return in_array($this->imageView, [ImageViews::GRID, ImageViews::LIST], true) ? $this->imageView : $default;
    }

    protected function imageViewSessionKey(): string
    {
        return 'admin.image-view.'.static::class;
    }
}
