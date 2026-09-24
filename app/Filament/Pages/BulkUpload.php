<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;


class BulkUpload extends Page
{
    protected string $view = 'filament.pages.unified-bulk-upload';


    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-s-cloud-arrow-up';


    protected static ?string $navigationLabel = 'Bulk Upload';


    /* Sits directly under the Dashboard, ahead of every group: it is how most images get in. */
    protected static ?int $navigationSort = -1;


    /*
     * Filing happens over a plain route rather than a method on this component:
     * a batch has to keep running after the admin navigates away, and by the
     * time the last file lands this component no longer exists.
     *
     * See App\Http\Controllers\Admin\BulkUploadProcessController.
     */


    public function getCloudinaryCloudName(): string
    {
        $cloud = env('CLOUDINARY_CLOUD_NAME', '');



        if (! empty($cloud)) {
            return $cloud;
        }



        $url = env('CLOUDINARY_URL');



        if ($url) {

            return parse_url(
                $url,
                PHP_URL_HOST
            ) ?? '';

        }



        return '';
    }



    public function getCloudinaryUploadPreset(): string
    {
        return env(
            'CLOUDINARY_UPLOAD_PRESET',
            ''
        );
    }
}
