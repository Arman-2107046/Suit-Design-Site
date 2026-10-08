<?php

namespace App\Services\BulkUpload;

use App\Services\BulkUploadService;

/*
 * The filename rules the bulk uploader files by, written out for the admin:
 * the dropdown on the Bulk Upload page and the PDF it can be downloaded as.
 *
 * Each rule mirrors one uploader in this folder. BulkUploadService::PRIORITY
 * decides the order (and tells the guide which prefixes exist), so a prefix
 * added there and missing here fails the NamingGuideTest instead of going
 * unexplained to the people who have to type it.
 */
final class NamingGuide
{
    /**
     * @return list<array{title: string, intro: string, rules: list<array{prefix: string, makes: string, pattern: string, example: string, parts: list<array{0: string, 1: string}>, needs: ?string, notes: list<string>, order: int}>}>
     */
    public static function groups(): array
    {
        $groups = [
            [
                'title' => 'Fabrics',
                'intro' => 'Cloths and their pictures. Upload these first: everything else is made in a fabric.',
                'rules' => [
                    self::rule('FAB', 'A fabric: its swatch picture, name and price', 'FAB_Price_Name[_1]', 'FAB_120_Blue Stripe.png', [
                        ['Price', 'A number, without a currency sign: 120 or 129.50'],
                        ['Name', 'The fabric name, spaces allowed'],
                        ['_1', 'Optional: makes it the default fabric the designer opens on'],
                    ], null, []),
                    self::rule('FPI', 'A preview picture in a fabric\'s gallery', 'FPI_Fabric[_Slot][_Caption]', 'FPI_Blue Stripe_2.png', [
                        ['Fabric', 'An existing fabric name. It must not contain an underscore'],
                        ['Slot', 'Optional, 1 to 10: replaces the picture in that slot. Without it the picture is added at the end'],
                        ['Caption', 'Optional text shown under the picture'],
                    ], 'The fabric (FAB)', ['Up to 10 preview pictures per fabric.']),
                    self::rule('RL', 'A real-life photo in a fabric\'s gallery', 'RL_Fabric[_Slot][_Caption]', 'RL_Blue Stripe_1_2 Piece Suit.png', [
                        ['Fabric', 'An existing fabric name. It must not contain an underscore'],
                        ['Slot', 'Optional, 1 to 10, as for FPI'],
                        ['Caption', 'Optional: "RL_Blue Stripe_2 Piece Suit.png" has the caption 2 Piece Suit'],
                    ], 'The fabric (FAB)', ['FRL_ works the same as RL_.', 'Up to 10 real-life pictures per fabric.']),
                ],
            ],
            [
                'title' => 'Linings',
                'intro' => 'Lining types, lining cloths and the lining pictures that sit inside each jacket.',
                'rules' => [
                    self::rule('LT', 'A lining type (for example Full Lining)', 'LT_Name', 'LT_Full Lining.png', [
                        ['Name', 'The lining type name, spaces allowed'],
                    ], null, []),
                    self::rule('CF', 'A lining cloth the customer can choose', 'CF_Name', 'CF_Blue Silk.png', [
                        ['Name', 'The lining cloth name, spaces allowed'],
                    ], null, []),
                    self::rule('CL', 'The picture of a lining type in a lining cloth', 'CL_LiningType_LiningCloth', 'CL_Full Lining_Blue Silk.png', [
                        ['LiningType', 'An existing lining type name'],
                        ['LiningCloth', 'An existing lining cloth name'],
                    ], 'The lining type (LT) and lining cloth (CF)', ['A custom lining is offered on every fabric, so no fabric is named.']),
                    self::rule('DL', 'The default lining picture of a body in a fabric', 'DL_BodyCode_LiningType_Fabric', 'DL_SB1_Default_Black Wool.png', [
                        ['BodyCode', 'The code of an existing body type, for example SB1'],
                        ['LiningType', 'An existing lining type name'],
                        ['Fabric', 'An existing fabric name, spaces allowed'],
                    ], 'The body type (BT), lining type (LT) and fabric (FAB)', []),
                ],
            ],
            [
                'title' => 'Jacket',
                'intro' => 'Body types, the jacket picture in each fabric, and the buttons.',
                'rules' => [
                    self::rule('BT', 'A body type (a jacket style), with its diagram', 'BT_Name_Code', 'BT_Single Breasted 1 Button_SB1.png', [
                        ['Name', 'The style name. Spaces or underscores both make spaces'],
                        ['Code', 'The short code after the last underscore, for example SB1. Other files refer to it'],
                    ], null, []),
                    self::rule('BD', 'The jacket body picture of a body type in a fabric', 'BD_BodyCode_Fabric[_1]', 'BD_SB1_Blue Stripe_1.png', [
                        ['BodyCode', 'The code of an existing body type'],
                        ['Fabric', 'An existing fabric name'],
                        ['_1', 'Optional: makes it the default body for that fabric'],
                    ], 'The body type (BT) and fabric (FAB)', []),
                    self::rule('BI', 'A button style, with its diagram', 'BI_Name', 'BI_Black Metal.png', [
                        ['Name', 'The button name. Spaces or underscores both make spaces'],
                    ], null, []),
                    self::rule('BB', 'A button style on a body type', 'BB_BodyTypeCode_ButtonName[_1]', 'BB_SB1_Black Metal_1.png', [
                        ['BodyTypeCode', 'The code of an existing body type'],
                        ['ButtonName', 'An existing button style name'],
                        ['_1', 'Optional: makes it the default button for that body type'],
                    ], 'The body type (BT) and button style (BI)', []),
                ],
            ],
            [
                'title' => 'Sleeves',
                'intro' => 'Shoulder styles and the sleeve picture in each fabric.',
                'rules' => [
                    self::rule('SLT', 'A sleeve (shoulder) type, with its diagram', 'SLT_Name_Code', 'SLT_English Shoulder_ES.png', [
                        ['Name', 'The shoulder name. Spaces or underscores both make spaces'],
                        ['Code', 'The short code after the last underscore, for example ES'],
                    ], null, []),
                    self::rule('SL', 'The sleeve picture of a type in a fabric', 'SL_TypeCode_Fabric[_1]', 'SL_ES_Blue Stripe.png', [
                        ['TypeCode', 'The code of an existing sleeve type'],
                        ['Fabric', 'An existing fabric name'],
                        ['_1', 'Optional: makes it the default sleeve for that fabric'],
                    ], 'The sleeve type (SLT) and fabric (FAB)', []),
                ],
            ],
            [
                'title' => 'Pockets',
                'intro' => 'Chest pockets and side pockets: a type with its diagram, then its picture in each fabric.',
                'rules' => [
                    self::rule('CPT', 'A chest pocket type, with its diagram', 'CPT_Name_Code', 'CPT_Welt_WELT.png', [
                        ['Name', 'The pocket name. Spaces or underscores both make spaces'],
                        ['Code', 'The short code after the last underscore, for example WELT'],
                    ], null, []),
                    self::rule('CP', 'The chest pocket picture of a type in a fabric', 'CP_TypeCode_Fabric[_1]', 'CP_WELT_Blue Stripe.png', [
                        ['TypeCode', 'The code of an existing chest pocket type'],
                        ['Fabric', 'An existing fabric name'],
                        ['_1', 'Optional: makes it the default chest pocket for that fabric'],
                    ], 'The chest pocket type (CPT) and fabric (FAB)', []),
                    self::rule('SPT', 'A side pocket type, with its diagram', 'SPT_Name_Code', 'SPT_Flap_FLAP.png', [
                        ['Name', 'The pocket name. Spaces or underscores both make spaces'],
                        ['Code', 'The short code after the last underscore, for example FLAP'],
                    ], null, []),
                    self::rule('SP', 'The side pocket picture of a type in a fabric', 'SP_TypeCode_Fabric[_1]', 'SP_FLAP_Blue Stripe_1.png', [
                        ['TypeCode', 'The code of an existing side pocket type'],
                        ['Fabric', 'An existing fabric name'],
                        ['_1', 'Optional: makes it the default side pocket for that fabric'],
                    ], 'The side pocket type (SPT) and fabric (FAB)', []),
                ],
            ],
            [
                'title' => 'Lapels',
                'intro' => 'Lapel styles, widths, and the lapel picture of each combination in each fabric.',
                'rules' => [
                    self::rule('LPC', 'A lapel style (Notch, Peak, Shawl…), with its diagram', 'LPC_Name', 'LPC_Notch.png', [
                        ['Name', 'The lapel style name'],
                    ], null, []),
                    self::rule('LPS', 'A lapel width (Slim, Classic, Wide…), with its diagram', 'LPS_Name', 'LPS_Classic.png', [
                        ['Name', 'The lapel width name'],
                    ], null, []),
                    self::rule('LP', 'The lapel picture of a body, style and width in a fabric', 'LP_BodyCode_Style_Width_Fabric[_1]', 'LP_SB1_Notch_Classic_Blue Stripe_1.png', [
                        ['BodyCode', 'The code of an existing body type'],
                        ['Style', 'An existing lapel style name (LPC)'],
                        ['Width', 'An existing lapel width name (LPS)'],
                        ['Fabric', 'An existing fabric name'],
                        ['_1', 'Optional: makes it the default lapel for that fabric'],
                    ], 'The body type (BT), the body in that fabric (BD), the lapel style (LPC), the width (LPS) and the fabric (FAB)', []),
                ],
            ],
        ];

        return $groups;
    }

    /** Every rule in the order the uploader files them. */
    public static function rules(): array
    {
        $rules = array_merge(...array_column(self::groups(), 'rules'));
        usort($rules, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $rules;
    }

    /** Rules that anyone typing a filename must follow whatever the prefix. */
    public static function general(): array
    {
        return [
            'The prefix comes first, in capital letters, then an underscore: write LP_, not lp_.',
            'Parts are separated by an underscore. Names themselves may contain spaces: Blue Stripe.',
            'Fabric, lining and button names must match the ones already in the catalogue, letter for letter.',
            'Codes (SB1, ES, WELT) are the ones set on the body, sleeve and pocket types.',
            'A trailing _1 marks the default. Without it the picture is not a default.',
            'Uploading a file again for the same item updates it instead of making a duplicate.',
            'PNG, JPG, WebP, GIF and SVG files are accepted.',
            'Upload in any order: the uploader files parents first, so a lapel never arrives ahead of its fabric.',
            'A file whose name does not follow its rule is refused with the reason shown in the list; nothing else is affected.',
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $parts
     * @param  list<string>  $notes
     */
    private static function rule(string $prefix, string $makes, string $pattern, string $example, array $parts, ?string $needs, array $notes): array
    {
        return [
            'prefix' => $prefix,
            'makes' => $makes,
            'pattern' => $pattern,
            'example' => $example,
            'parts' => $parts,
            'needs' => $needs,
            'notes' => $notes,
            'order' => BulkUploadService::PRIORITY[$prefix] ?? 999,
        ];
    }
}
