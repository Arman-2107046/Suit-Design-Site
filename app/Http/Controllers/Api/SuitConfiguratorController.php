<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomLining;
use App\Models\DesignerSetting;
use App\Models\Fabric;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SuitConfiguratorController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Payload cache
    |--------------------------------------------------------------------------
    |
    | Building the configurator payload touches ~20 tables. The serialized
    | result is cached and invalidated by the BustsConfiguratorCache trait
    | on every configurator model; the TTL is only a safety net for writes
    | that bypass Eloquent events (raw query-builder updates).
    |
    */
    public const CACHE_KEY = 'configurator.payload';

    private const CACHE_TTL_SECONDS = 60 * 60 * 12;

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function index(Request $request): Response
    {
        $json = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => json_encode($this->buildPayload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        // Weak ETag so a warm browser revalidates with a 304 instead of
        // re-downloading the whole payload on every visit.
        $etag = 'W/"' . md5($json) . '"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'ETag' => $etag,
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    private function buildPayload(): array
    {
        $fabrics = Fabric::with([
            'images',
            'latestInfo',
            /*
            |--------------------------------------------------------------------------
            | Fabric-level options
            |--------------------------------------------------------------------------
            */
            'sleeves.sleeveType',
            'sidePockets.sidePocketType',
            'chestPockets.chestPocketType',

            /*
            |--------------------------------------------------------------------------
            | Default Linings
            |--------------------------------------------------------------------------
            |
            | Default lining is related to:
            | fabric_id + body_type_id
            |
            */
            'defaultLinings.liningType',
            'defaultLinings.bodyType',

            /*
            |--------------------------------------------------------------------------
            | Bodies
            |--------------------------------------------------------------------------
            */
            'body.bodyType',

            /*
            |--------------------------------------------------------------------------
            | Body-level options
            |--------------------------------------------------------------------------
            */
            'body.lapels.lapelCategory',
            'body.lapels.lapelSubcategory',

            /*
            |--------------------------------------------------------------------------
            | Body Type-level options
            |--------------------------------------------------------------------------
            */
            'body.bodyType.bodyButtons.buttonImage',
        ])
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Custom Linings
        |--------------------------------------------------------------------------
        |
        | Tied to no fabric, so they are read once and offered on every one of
        | them. Repeating the list per fabric keeps the shape the designer
        | already reads, and keeps a lining's id stable when the fabric changes
        | so the customer's choice survives the switch.
        |
        */

        $customLinings = CustomLining::query()
            ->with(['customLiningFabric', 'liningType'])
            ->where('status', true)
            ->inDragOrder()
            ->get()
            ->map(function ($lining) {

                return [
                    'id' => $lining->id,

                    'image' => $lining->image,

                    'layer_index' => $lining->layer_index,

                    'is_default' => $lining->is_default,

                    'fabric' => $lining->customLiningFabric
                        ? [
                            'id' => $lining->customLiningFabric->id,
                            'name' => $lining->customLiningFabric->name,
                            'image' => $lining->customLiningFabric->image,
                        ]
                        : null,

                    'type' => $lining->liningType
                        ? [
                            'id' => $lining->liningType->id,
                            'name' => $lining->liningType->name,
                            'code' => $lining->liningType->code,
                            'diagram' => $lining->liningType->diagram,
                        ]
                        : null,
                ];
            })
            ->values()
            ->all();


        return [
            'success' => true,

            /* How the admin wants the option lists laid out. */
            'layout' => DesignerSetting::current()->toLayoutArray(),

            'data' => $fabrics->map(function ($fabric) use ($customLinings) {

                return [

                    /*
                    |--------------------------------------------------------------------------
                    | Fabric
                    |--------------------------------------------------------------------------
                    */

                    'id' => $fabric->id,
                    'name' => $fabric->name,
                    'price' => $fabric->price,
                    'image' => $fabric->image,
                    'is_default' => $fabric->is_default,
                    'is_new' => (bool) $fabric->is_new,
                    'info' => $fabric->latestInfo?->toCardArray(),
                    'preview_images' => $fabric->images->where('kind', 'preview')->pluck('url')->values()->all(),
                    'real_life_images' => $fabric->images->where('kind', 'real_life')->map(fn ($i) => ['url' => $i->url, 'caption' => $i->caption])->values()->all(),


                    /*
                    |--------------------------------------------------------------------------
                    | Sleeves
                    |--------------------------------------------------------------------------
                    */

                    'sleeves' => $fabric->sleeves
                        ->where('status', true)
                        ->values()
                        ->map(function ($sleeve) {

                            return [
                                'id' => $sleeve->id,
                                'image' => $sleeve->image,
                                'layer_index' => $sleeve->layer_index,
                                'is_default' => $sleeve->is_default,

                                'type' => $sleeve->sleeveType
                                    ? [
                                        'id' => $sleeve->sleeveType->id,
                                        'name' => $sleeve->sleeveType->name,
                                        'code' => $sleeve->sleeveType->code,
                                        'diagram' => $sleeve->sleeveType->diagram,
                                    ]
                                    : null,
                            ];
                        }),


                    /*
                    |--------------------------------------------------------------------------
                    | Side Pockets
                    |--------------------------------------------------------------------------
                    */

                    'side_pockets' => $fabric->sidePockets
                        ->where('status', true)
                        ->values()
                        ->map(function ($pocket) {

                            return [
                                'id' => $pocket->id,
                                'image' => $pocket->image,
                                'layer_index' => $pocket->layer_index,
                                'is_default' => $pocket->is_default,

                                'type' => $pocket->sidePocketType
                                    ? [
                                        'id' => $pocket->sidePocketType->id,
                                        'name' => $pocket->sidePocketType->name,
                                        'code' => $pocket->sidePocketType->code,
                                        'diagram' => $pocket->sidePocketType->diagram,
                                    ]
                                    : null,
                            ];
                        }),


                    /*
                    |--------------------------------------------------------------------------
                    | Chest Pockets
                    |--------------------------------------------------------------------------
                    */

                    'chest_pockets' => $fabric->chestPockets
                        ->where('status', true)
                        ->values()
                        ->map(function ($pocket) {

                            return [
                                'id' => $pocket->id,
                                'image' => $pocket->image,
                                'layer_index' => $pocket->layer_index,
                                'is_default' => $pocket->is_default,

                                'type' => $pocket->chestPocketType
                                    ? [
                                        'id' => $pocket->chestPocketType->id,
                                        'name' => $pocket->chestPocketType->name,
                                        'code' => $pocket->chestPocketType->code,
                                        'diagram' => $pocket->chestPocketType->diagram,
                                    ]
                                    : null,
                            ];
                        }),


                    /*
                    |--------------------------------------------------------------------------
                    | Custom Linings
                    |--------------------------------------------------------------------------
                    */

                    'custom_linings' => $customLinings,


                    /*
                    |--------------------------------------------------------------------------
                    | Bodies
                    |--------------------------------------------------------------------------
                    */

                    'bodies' => $fabric->body
                        ->where('status', true)
                        ->values()
                        ->map(function ($body) use ($fabric) {

                            $bodyType = $body->bodyType;

                            /*
                            |--------------------------------------------------------------------------
                            | Default Linings
                            |--------------------------------------------------------------------------
                            |
                            | Default lining is determined by:
                            |
                            | fabric_id + body_type_id
                            |
                            */

                            $defaultLinings = $fabric->defaultLinings
                                ->where('body_type_id', $body->body_type_id)
                                ->where('status', true)
                                ->values();

                            return [

                                /*
                                |--------------------------------------------------------------------------
                                | Body
                                |--------------------------------------------------------------------------
                                */

                                'id' => $body->id,

                                'fabric_id' => $body->fabric_id,

                                'body_type_id' => $body->body_type_id,

                                'image' => $body->image,

                                'layer_index' => $body->layer_index,

                                'is_default' => $body->is_default,


                                /*
                                |--------------------------------------------------------------------------
                                | Body Type
                                |--------------------------------------------------------------------------
                                */

                                'body_type' => $bodyType
                                    ? [

                                        'id' => $bodyType->id,

                                        'name' => $bodyType->name,

                                        'code' => $bodyType->code,

                                        'diagram' => $bodyType->diagram,


                                        /*
                                        |--------------------------------------------------------------------------
                                        | Body Buttons
                                        |--------------------------------------------------------------------------
                                        */

                                        'body_buttons' => $bodyType->bodyButtons
                                            ->where('status', true)
                                            ->values()
                                            ->map(function ($button) {

                                                return [

                                                    'id' => $button->id,

                                                    'body_type_id' =>
                                                    $button->body_type_id,

                                                    'button_image_id' =>
                                                    $button->button_image_id,

                                                    'image' =>
                                                    $button->image,

                                                    'layer_index' =>
                                                    $button->layer_index,

                                                    'is_default' =>
                                                    $button->is_default,


                                                    /*
                                                    |--------------------------------------------------------------------------
                                                    | Button Image
                                                    |--------------------------------------------------------------------------
                                                    */

                                                    'button_image' =>
                                                    $button->buttonImage
                                                        ? [

                                                            'id' =>
                                                            $button->buttonImage->id,

                                                            'name' =>
                                                            $button->buttonImage->name,

                                                            'diagram' =>
                                                            $button->buttonImage->diagram,

                                                        ]
                                                        : null,
                                                ];
                                            }),

                                    ]
                                    : null,


                                /*
                                |--------------------------------------------------------------------------
                                | Default Linings
                                |--------------------------------------------------------------------------
                                */

                                'default_linings' => $defaultLinings
                                    ->map(function ($lining) {

                                        return [

                                            'id' => $lining->id,

                                            'fabric_id' =>
                                            $lining->fabric_id,

                                            'body_type_id' =>
                                            $lining->body_type_id,

                                            'lining_type_id' =>
                                            $lining->lining_type_id,

                                            'image' =>
                                            $lining->image,

                                            'layer_index' =>
                                            $lining->layer_index,

                                            'status' =>
                                            $lining->status,


                                            /*
                                            |--------------------------------------------------------------------------
                                            | Lining Type
                                            |--------------------------------------------------------------------------
                                            */

                                            'type' => $lining->liningType
                                                ? [

                                                    'id' =>
                                                    $lining->liningType->id,

                                                    'name' =>
                                                    $lining->liningType->name,

                                                    'code' =>
                                                    $lining->liningType->code,

                                                    'diagram' =>
                                                    $lining->liningType->diagram,

                                                ]
                                                : null,
                                        ];
                                    }),


                                /*
|--------------------------------------------------------------------------
| Lapels
|--------------------------------------------------------------------------
*/

                                'lapels' => $body->lapels
                                    ->where('status', true)
                                    ->values()
                                    ->map(function ($lapel) {

                                        return [

                                            'id' => $lapel->id,

                                            'image' => $lapel->image,

                                            'layer_index' => $lapel->layer_index,

                                            'is_default' => $lapel->is_default,


                                            /*
            |--------------------------------------------------------------------------
            | Lapel Category
            |--------------------------------------------------------------------------
            */

                                            'category' => $lapel->lapelCategory
                                                ? [

                                                    'id' => $lapel->lapelCategory->id,

                                                    'name' => $lapel->lapelCategory->name,

                                                    'diagram' => $lapel->lapelCategory->diagram,

                                                    'is_default' => $lapel->lapelCategory->is_default,

                                                ]
                                                : null,


                                            /*
            |--------------------------------------------------------------------------
            | Lapel Subcategory
            |--------------------------------------------------------------------------
            */

                                            'subcategory' => $lapel->lapelSubcategory
                                                ? [

                                                    'id' => $lapel->lapelSubcategory->id,

                                                    'name' => $lapel->lapelSubcategory->name,

                                                    'diagram' => $lapel->lapelSubcategory->diagram,

                                                    'is_default' => $lapel->lapelSubcategory->is_default,

                                                ]
                                                : null,
                                        ];
                                    }),




                            ];
                        }),
                ];
            }),
        ];
    }
}
