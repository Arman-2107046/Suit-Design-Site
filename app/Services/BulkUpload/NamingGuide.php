<?php

namespace App\Services\BulkUpload;

use App\Services\BulkUploadService;

/*
 * The filename rules the bulk uploader files by, kept short for the admin:
 * the dropdown on the Bulk Upload page and the PDF it can be downloaded as.
 *
 * Each rule mirrors one uploader in this folder. BulkUploadService::PRIORITY
 * decides the order (and tells the guide which prefixes exist), so a prefix
 * added there and missing here fails the NamingGuideTest.
 */
final class NamingGuide
{
    /**
     * @return list<array{title: string, rules: list<array{prefix: string, makes: string, pattern: string, example: string, order: int}>}>
     */
    public static function groups(): array
    {
        return [
            ['title' => 'Fabrics', 'rules' => [
                self::rule('FAB', 'Fabric', 'FAB_Price_Name[_1]', 'FAB_120_Blue Stripe.png'),
                self::rule('FPI', 'Preview picture', 'FPI_Fabric[_Slot]', 'FPI_Blue Stripe_2.png'),
                self::rule('RL', 'Real-life photo', 'RL_Fabric[_Slot][_Caption]', 'RL_Blue Stripe_1_2 Piece Suit.png'),
            ]],
            ['title' => 'Linings', 'rules' => [
                self::rule('LT', 'Lining type', 'LT_Name', 'LT_Full Lining.png'),
                self::rule('CF', 'Lining cloth', 'CF_Name', 'CF_Blue Silk.png'),
                self::rule('CL', 'Custom lining', 'CL_LiningType_LiningCloth', 'CL_Full Lining_Blue Silk.png'),
                self::rule('DL', 'Default lining', 'DL_BodyCode_LiningType_Fabric', 'DL_SB1_Default_Black Wool.png'),
                self::rule('UL', 'Unlined', 'UL_Unlined_Fabric', 'UL_Unlined_Blue Stripe.png'),
                self::rule('ULP', 'Unlined plate', 'ULP_Unlined Plate_Fabric', 'ULP_Unlined Plate_Blue Stripe.png'),
            ]],
            ['title' => 'Jacket', 'rules' => [
                self::rule('BT', 'Body type', 'BT_Name_Code', 'BT_Single Breasted 1 Button_SB1.png'),
                self::rule('BD', 'Jacket body', 'BD_BodyCode_Fabric[_1]', 'BD_SB1_Blue Stripe_1.png'),
                self::rule('BI', 'Button style', 'BI_Name', 'BI_Black Metal.png'),
                self::rule('BB', 'Button on a body', 'BB_BodyCode_Button[_1]', 'BB_SB1_Black Metal_1.png'),
            ]],
            ['title' => 'Sleeves', 'rules' => [
                self::rule('SLT', 'Sleeve type', 'SLT_Name_Code', 'SLT_English Shoulder_ES.png'),
                self::rule('SL', 'Sleeve', 'SL_Code_Fabric[_1]', 'SL_ES_Blue Stripe.png'),
            ]],
            ['title' => 'Pockets', 'rules' => [
                self::rule('CPT', 'Chest pocket type', 'CPT_Name_Code', 'CPT_Welt_WELT.png'),
                self::rule('CP', 'Chest pocket', 'CP_Code_Fabric[_1]', 'CP_WELT_Blue Stripe.png'),
                self::rule('SPT', 'Side pocket type', 'SPT_Name_Code', 'SPT_Flap_FLAP.png'),
                self::rule('SP', 'Side pocket', 'SP_Code_Fabric[_1]', 'SP_FLAP_Blue Stripe_1.png'),
            ]],
            ['title' => 'Lapels', 'rules' => [
                self::rule('LPC', 'Lapel style', 'LPC_Name', 'LPC_Notch.png'),
                self::rule('LPS', 'Lapel width', 'LPS_Name', 'LPS_Classic.png'),
                self::rule('LP', 'Lapel', 'LP_BodyCode_Style_Width_Fabric[_1]', 'LP_SB1_Notch_Classic_Blue Stripe_1.png'),
            ]],
        ];
    }

    /** Every rule in the order the uploader files them. */
    public static function rules(): array
    {
        $rules = array_merge(...array_column(self::groups(), 'rules'));
        usort($rules, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $rules;
    }

    /** The few rules behind every filename. */
    public static function general(): array
    {
        return [
            'Prefix in capitals, then an underscore: write LP_, not lp_.',
            'Parts are separated by an underscore; names may contain spaces.',
            'A trailing _1 marks the default.',
            'Names and codes must match the ones already in the catalogue.',
        ];
    }

    private static function rule(string $prefix, string $makes, string $pattern, string $example): array
    {
        return [
            'prefix' => $prefix,
            'makes' => $makes,
            'pattern' => $pattern,
            'example' => $example,
            'order' => BulkUploadService::PRIORITY[$prefix] ?? 999,
        ];
    }
}
