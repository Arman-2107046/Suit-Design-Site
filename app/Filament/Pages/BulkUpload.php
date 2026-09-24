<?php

namespace App\Filament\Pages;

use App\Services\BulkUploadService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;


class BulkUpload extends Page
{
    protected string $view = 'filament.pages.unified-bulk-upload';


    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-s-cloud-arrow-up';


    protected static ?string $navigationLabel = 'Bulk Upload';


    /* Sits directly under the Dashboard, ahead of every group: it is how most images get in. */
    protected static ?int $navigationSort = -1;



    /**
     * Filename prefixes, as the completion screen names them.
     */
    private const DESTINATIONS = [
        'FAB' => 'fabrics',
        'FPI' => 'fabric images',
        'RL' => 'fabric images',
        'FRL' => 'fabric images',
        'LT' => 'lining types',
        'CF' => 'custom lining fabrics',
        'CL' => 'custom linings',
        'BT' => 'body types',
        'BD' => 'bodies',
        'DL' => 'default linings',
        'BI' => 'button images',
        'BB' => 'body buttons',
        'SLT' => 'sleeve types',
        'SL' => 'sleeves',
        'CPT' => 'chest pocket types',
        'CP' => 'chest pockets',
        'SPT' => 'side pocket types',
        'SP' => 'side pockets',
        'LPC' => 'lapel categories',
        'LPS' => 'lapel subcategories',
        'LP' => 'lapels',
    ];


    /**
     * @return array{filed: int, failed: int, breakdown: array<string, int>, rejected: list<array{file: string, reason: string}>}
     */
    public function processUploads(array $files): array
    {
        $service = app(BulkUploadService::class);


        $result = $service->process($files);



        Notification::make()
            ->title('Bulk Upload Completed')
            ->body(
                $result['message'] ?? 'Upload completed'
            )
            ->success()
            ->send();



        return $this->summarise($result);
    }



    /**
     * Count what landed where, and carry the reasons for anything that did not,
     * so the completion screen can say more than "done" and the PDF report has
     * something to list.
     *
     * @return array{filed: int, failed: int, breakdown: array<string, int>, rejected: list<array{file: string, reason: string}>}
     */
    private function summarise(array $result): array
    {
        $breakdown = [];

        $filed = 0;


        foreach ($result['results'] ?? [] as $entry) {

            if (($entry['result']['success'] ?? false) !== true) {
                continue;
            }


            $prefix = explode(
                '_',
                pathinfo($entry['file'], PATHINFO_FILENAME)
            )[0];


            $label = self::DESTINATIONS[$prefix] ?? 'other';


            $breakdown[$label] = ($breakdown[$label] ?? 0) + 1;

            $filed++;
        }


        arsort($breakdown);


        $rejected = array_map(
            fn (array $failure): array => [
                'file' => $failure['file'],
                'reason' => $failure['message'] ?? 'Unknown error',
            ],
            $result['failed'] ?? []
        );


        return [
            'filed' => $filed,
            'failed' => count($rejected),
            'breakdown' => $breakdown,
            'rejected' => array_values($rejected),
        ];
    }



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
