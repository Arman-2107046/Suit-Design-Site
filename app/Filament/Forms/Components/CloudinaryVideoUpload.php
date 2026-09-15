<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/*
 * Uploads straight from the browser to Cloudinary with the unsigned preset,
 * so videos never pass through PHP's upload limits. State is the secure URL.
 */
class CloudinaryVideoUpload extends Field
{
    protected string $view = 'filament.forms.components.cloudinary-video-upload';

    protected string $folder = 'homepage';

    protected int $maxMegabytes = 200;

    public function folder(string $folder): static
    {
        $this->folder = $folder;

        return $this;
    }

    public function maxMegabytes(int $megabytes): static
    {
        $this->maxMegabytes = $megabytes;

        return $this;
    }

    public function getFolder(): string
    {
        return $this->folder;
    }

    public function getMaxMegabytes(): int
    {
        return $this->maxMegabytes;
    }

    public function getCloudName(): string
    {
        return (string) parse_url((string) config('cloudinary.cloud_url'), PHP_URL_HOST);
    }

    public function getUploadPreset(): string
    {
        return (string) config('cloudinary.upload_preset');
    }
}
