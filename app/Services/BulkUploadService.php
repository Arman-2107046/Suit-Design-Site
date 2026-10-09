<?php

namespace App\Services;

use App\Services\BulkUpload\BodiesUploader;
use App\Services\BulkUpload\BodyTypesUploader;
use App\Services\BulkUpload\ButtonImagesUploader;
use App\Services\BulkUpload\ChestPocketTypesUploader;
use App\Services\BulkUpload\FabricImagesUploader;
use App\Services\BulkUpload\FabricsUploader;
use App\Services\BulkUpload\ChestPocketsUploader;
use App\Services\BulkUpload\LapelCategoriesUploader;
use App\Services\BulkUpload\LapelSubcategoriesUploader;
use App\Services\BulkUpload\LapelsUploader;
use App\Services\BulkUpload\SidePocketTypesUploader;
use App\Services\BulkUpload\SidePocketsUploader;
use App\Services\BulkUpload\SleevesUploader;
use App\Services\BulkUpload\BodyButtonsUploader;
use App\Services\BulkUpload\SleeveTypesUploader;
use App\Services\BulkUpload\LiningTypesUploader;
use App\Services\BulkUpload\DefaultLiningsUploader;
use App\Services\BulkUpload\CustomLiningFabricsUploader;
use App\Services\BulkUpload\CustomLiningsUploader;
use App\Models\UnlinedLining;
use App\Services\BulkUpload\UnlinedLiningsUploader;

class BulkUploadService
{
    /**
     * The order files are filed in: everything a file refers to is filed
     * before it, so a lapel never arrives ahead of its category and a fabric's
     * pictures never arrive ahead of the fabric.
     *
     * The bulk uploader sorts by this too, before it splits a large batch into
     * chunks — otherwise a dependency could land in a later request.
     */
    public const PRIORITY = [
        'FAB' => 1,
        'FPI' => 2,
        'RL' => 2,
        'FRL' => 2,

        'LT' => 3,
        'CF' => 4,
        'CL' => 5,

        'BT' => 6,
        'BD' => 7,
        'DL' => 8,
        'UL' => 8,
        'ULP' => 8,

        'BI' => 9,
        'BB' => 10,

        'SLT' => 11,
        'SL' => 12,

        'CPT' => 13,
        'CP' => 14,

        'SPT' => 15,
        'SP' => 16,

        'LPC' => 17,
        'LPS' => 18,
        'LP' => 19,
    ];

    /** Where a filename sorts; anything unrecognised goes last. */
    public static function priorityOf(string $filename): int
    {
        $prefix = explode('_', pathinfo($filename, PATHINFO_FILENAME))[0];

        return self::PRIORITY[$prefix] ?? 999;
    }

    public function process(array $files): array
    {
        /* Parents before the things that point at them; see PRIORITY. */
        usort($files, fn ($a, $b) => self::priorityOf($a['name']) <=> self::priorityOf($b['name']));


        /*
        |--------------------------------------------------------------------------
        | Store results
        |--------------------------------------------------------------------------
        */

        $results = [];

        $failed = [];


        /*
        |--------------------------------------------------------------------------
        | Process uploads
        |--------------------------------------------------------------------------
        */

        foreach ($files as $file) {

            $filename = pathinfo(
                $file['name'],
                PATHINFO_FILENAME
            );

            $prefix = explode(
                '_',
                $filename
            )[0];


            /*
            |--------------------------------------------------------------------------
            | Process current file
            |--------------------------------------------------------------------------
            */

            $result = match ($prefix) {

                'FAB' =>
                    app(FabricsUploader::class)
                        ->handle($file),

                'FPI', 'RL', 'FRL' =>
                    app(FabricImagesUploader::class)
                        ->handle($file),

                'LT' =>
                    app(LiningTypesUploader::class)
                        ->handle($file),

                'CF' =>
                    app(CustomLiningFabricsUploader::class)
                        ->handle($file),

                'CL' =>
                    app(CustomLiningsUploader::class)
                        ->handle($file),

                'BT' =>
                    app(BodyTypesUploader::class)
                        ->handle($file),

                'BD' =>
                    app(BodiesUploader::class)
                        ->handle($file),

                'DL' =>
                    app(DefaultLiningsUploader::class)
                        ->handle($file),

                'UL' =>
                    app(UnlinedLiningsUploader::class)
                        ->handle($file),

                'ULP' =>
                    app(UnlinedLiningsUploader::class)
                        ->handle($file, UnlinedLining::PLATE),

                'BI' =>
                    app(ButtonImagesUploader::class)
                        ->handle($file),

                'BB' =>
                    app(BodyButtonsUploader::class)
                        ->handle($file),

                'SLT' =>
                    app(SleeveTypesUploader::class)
                        ->handle($file),

                'SL' =>
                    app(SleevesUploader::class)
                        ->handle($file),

                'CPT' =>
                    app(ChestPocketTypesUploader::class)
                        ->handle($file),

                'CP' =>
                    app(ChestPocketsUploader::class)
                        ->handle($file),

                'SPT' =>
                    app(SidePocketTypesUploader::class)
                        ->handle($file),

                'SP' =>
                    app(SidePocketsUploader::class)
                        ->handle($file),

                'LPC' =>
                    app(LapelCategoriesUploader::class)
                        ->handle($file),

                'LPS' =>
                    app(LapelSubcategoriesUploader::class)
                        ->handle($file),

                'LP' =>
                    app(LapelsUploader::class)
                        ->handle($file),

                default => [
                    'success' => false,
                    'message' => "Unknown upload prefix: {$prefix}",
                ],
            };


            /*
            |--------------------------------------------------------------------------
            | Save result
            |--------------------------------------------------------------------------
            */

            $results[] = [
                'file' => $file['name'],
                'result' => $result,
            ];


            /*
            |--------------------------------------------------------------------------
            | Track failures
            |--------------------------------------------------------------------------
            */

            if (($result['success'] ?? false) !== true) {

                $failed[] = [
                    'file' => $file['name'],
                    'message' => $result['message'] ?? 'Unknown error',
                    'debug' => $result['debug'] ?? null,
                ];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Return failures
        |--------------------------------------------------------------------------
        */

        if (count($failed) > 0) {

            return [
                'success' => false,

                'message' => count($failed)
                    . ' file(s) failed during processing.',

                'failed' => $failed,

                'results' => $results,
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Everything succeeded
        |--------------------------------------------------------------------------
        */

        return [
            'success' => true,

            'message' => 'All images processed successfully.',

            'results' => $results,
        ];
    }
}
