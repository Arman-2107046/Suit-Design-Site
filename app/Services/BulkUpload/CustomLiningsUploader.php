<?php

namespace App\Services\BulkUpload;

use App\Models\CustomLining;
use App\Models\CustomLiningFabric;
use App\Models\LiningType;

class CustomLiningsUploader
{
    public function handle(array $file): array
    {
        $originalName = $file['name'] ?? null;
        $url = $file['url'] ?? null;

        if (! $originalName || ! $url) {
            return [
                'success' => false,
                'message' => 'Invalid file data',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Remove extension
        |--------------------------------------------------------------------------
        |
        | CL_Full Lining_Blue Silk.png
        |
        | becomes:
        |
        | CL_Full Lining_Blue Silk
        |
        */

        $filename = pathinfo(
            $originalName,
            PATHINFO_FILENAME
        );

        /*
        |--------------------------------------------------------------------------
        | Split filename
        |--------------------------------------------------------------------------
        |
        | Expected:
        |
        | CL_LiningType_LiningFabric
        |
        | Example:
        |
        | CL_Full Lining_Blue Silk
        |
        | A custom lining is offered on every fabric, so nothing names one here.
        |
        */

        $parts = explode('_', $filename);

        if (count($parts) !== 3) {
            return [
                'success' => false,
                'message' =>
                    "{$originalName} — invalid format. Expected: CL_LiningType_LiningFabric",

                'debug' => [
                    'filename' => $originalName,
                    'parts' => $parts,
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Identifier
        |--------------------------------------------------------------------------
        */

        $identifier = strtoupper(
            trim($parts[0])
        );

        if ($identifier !== 'CL') {
            return [
                'success' => false,
                'message' =>
                    "{$originalName} — invalid identifier. Expected CL.",

                'debug' => [
                    'identifier' => $identifier,
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve Names
        |--------------------------------------------------------------------------
        |
        | parts[1] = Lining Type Name
        | parts[2] = Custom Lining Fabric Name
        |
        */

        $liningTypeName = trim(
            $parts[1]
        );

        $customLiningFabricName = trim(
            $parts[2]
        );

        /*
        |--------------------------------------------------------------------------
        | Find Lining Type
        |--------------------------------------------------------------------------
        */

        $liningType = LiningType::where(
            'name',
            $liningTypeName
        )->first();

        if (! $liningType) {
            return [
                'success' => false,
                'message' =>
                    "{$liningTypeName} lining type not found",

                'debug' => [
                    'filename' => $originalName,
                    'lining_type_searched' => $liningTypeName,
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Find Custom Lining Fabric
        |--------------------------------------------------------------------------
        */

        $customLiningFabric = CustomLiningFabric::where(
            'name',
            $customLiningFabricName
        )->first();

        if (! $customLiningFabric) {
            return [
                'success' => false,
                'message' =>
                    "{$customLiningFabricName} custom lining fabric not found",

                'debug' => [
                    'filename' => $originalName,
                    'custom_lining_fabric_searched' =>
                        $customLiningFabricName,
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Create / Update Custom Lining
        |--------------------------------------------------------------------------
        |
        | Unique combination:
        |
        | custom_lining_fabric_id
        | lining_type_id
        |
        */

        $customLining = CustomLining::updateOrCreate(
            [
                'custom_lining_fabric_id' =>
                    $customLiningFabric->id,

                'lining_type_id' =>
                    $liningType->id,
            ],
            [
                'image' => $url,

                'layer_index' => 100,

                'is_default' => false,

                'status' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        return [
            'success' => true,

            'message' =>
                "{$originalName} imported successfully.",

            'debug' => [

                'filename' => $originalName,

                'lining_type' => [
                    'id' => $liningType->id,
                    'name' => $liningType->name,
                ],

                'custom_lining_fabric' => [
                    'id' => $customLiningFabric->id,
                    'name' => $customLiningFabric->name,
                ],

                'custom_lining' => [
                    'id' => $customLining->id,
                    'image' => $customLining->image,
                    'layer_index' => $customLining->layer_index,
                    'is_default' => $customLining->is_default,
                    'status' => $customLining->status,
                ],
            ],
        ];
    }
}
