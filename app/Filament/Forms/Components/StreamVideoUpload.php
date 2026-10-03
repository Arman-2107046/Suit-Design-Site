<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/*
 * Uploads a video straight from the browser to Cloudflare Stream, so it never
 * passes through PHP's upload limits, then waits for Stream to finish encoding
 * and saves the address of the MP4 the homepage plays.
 */
class StreamVideoUpload extends Field
{
    protected string $view = 'filament.forms.components.stream-video-upload';

    /* Stream's basic upload takes up to 200 MB in one request. */
    protected int $maxMegabytes = 200;

    public function maxMegabytes(int $megabytes): static
    {
        $this->maxMegabytes = min($megabytes, 200);

        return $this;
    }

    public function getMaxMegabytes(): int
    {
        return $this->maxMegabytes;
    }
}
