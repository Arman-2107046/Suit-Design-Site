<?php

namespace App\Services\BulkUpload;

use App\Models\Fabric;
use App\Models\LiningType;
use App\Models\UnlinedLining;

/*
 * UL_Unlined_Blue Stripe.png        a fabric with no lining
 * ULP_Unlined Plate_Blue Stripe.png the same, with the plate
 *
 * The lining type is named in the filename ("Unlined", "Unlined Plate"), the
 * rest of it is the fabric. One of each kind per fabric, the same for every
 * jacket style; uploading again replaces it.
 */
class UnlinedLiningsUploader
{
    public function handle(array $file, string $kind = UnlinedLining::UNLINED): array
    {
        $originalName = $file['name'] ?? null;
        $url = $file['url'] ?? null;

        if (! $originalName || ! $url) {
            return ['success' => false, 'message' => 'Invalid file data'];
        }

        $prefix = $kind === UnlinedLining::PLATE ? 'ULP' : 'UL';
        $example = $kind === UnlinedLining::PLATE ? 'ULP_Unlined Plate_FabricName' : 'UL_Unlined_FabricName';

        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $parts = explode('_', preg_replace('/^'.$prefix.'_/', '', $filename));

        if (count($parts) < 2 || trim($parts[0]) === '' || trim(implode('_', array_slice($parts, 1))) === '') {
            return [
                'success' => false,
                'message' => "{$originalName} — invalid format. Expected: {$example}",
                'debug' => ['filename' => $originalName],
            ];
        }

        $liningTypeName = trim($parts[0]);

        /* The fabric may contain underscores, as in the other fabric-named files. */
        $fabricName = trim(str_replace('_', ' ', implode('_', array_slice($parts, 1))));

        $liningType = LiningType::where('name', $liningTypeName)->first();

        if (! $liningType) {
            return [
                'success' => false,
                'message' => "{$liningTypeName} lining type not found",
                'debug' => ['filename' => $originalName, 'lining_type_searched' => $liningTypeName],
            ];
        }

        $fabric = Fabric::where('name', $fabricName)->first();

        if (! $fabric) {
            return [
                'success' => false,
                'message' => "{$fabricName} fabric not found",
                'debug' => ['filename' => $originalName, 'fabric_searched' => $fabricName],
            ];
        }

        UnlinedLining::updateOrCreate(
            ['fabric_id' => $fabric->id, 'kind' => $kind],
            [
                'lining_type_id' => $liningType->id,
                'image' => $url,
                'layer_index' => 100,
                'status' => true,
            ]
        );

        return [
            'success' => true,
            'message' => "{$originalName} imported successfully",
            'debug' => [
                'filename' => $originalName,
                'lining_type' => ['id' => $liningType->id, 'name' => $liningType->name],
                'fabric' => ['id' => $fabric->id, 'name' => $fabric->name],
            ],
        ];
    }
}
