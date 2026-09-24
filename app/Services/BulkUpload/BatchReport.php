<?php

namespace App\Services\BulkUpload;

class BatchReport
{
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
     * Count what landed where, and carry the reasons for anything that did not,
     * so the completion screen can say more than "done" and the downloadable
     * report has something to list.
     *
     * @return array{filed: int, failed: int, breakdown: array<string, int>, rejected: list<array{file: string, reason: string}>}
     */
    public function summarise(array $result): array
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
}
