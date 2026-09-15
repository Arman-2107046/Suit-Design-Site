<?php

namespace App\Support;

/*
 * Estimates garment measurements from height, weight and age. These are
 * anthropometric approximations — a starting point the customer reviews and
 * edits, and which the tailor confirms — not a fitting.
 */
class BodyEstimator
{
    public const FIELDS = [
        'sleeve_length' => 'Sleeve length',
        'shoulder_width' => 'Shoulder width',
        'chest' => 'Chest around',
        'stomach' => 'Stomach',
        'hips' => 'Hips',
        'neck' => 'Neck',
        'torso_length' => 'Torso length',
        'bicep' => 'Bicep around',
        'leg_length' => 'Leg length',
        'pants_waist' => 'Pants waist',
        'thigh' => 'Thigh',
        'rise' => 'Rise',
    ];

    public const LIMITS = [
        'height' => [140, 220],
        'weight' => [40, 200],
        'age' => [16, 100],
    ];

    /** @return array<string, float> centimetres, to the nearest 0.5 */
    public static function estimate(int $height, int $weight, int $age): array
    {
        $bmi = $weight / (($height / 100) ** 2);
        $b = $bmi - 22;          // deviation from a lean build
        $a = max(0, $age - 30);  // weight settles around the middle with age

        $raw = [
            'sleeve_length' => $height * 0.36,
            'shoulder_width' => $height * 0.27 + $b * 0.4,
            'chest' => $height * 0.53 + $b * 2.4,
            'stomach' => $height * 0.47 + $b * 2.9 + $a * 0.08,
            'hips' => $height * 0.555 + $b * 1.4,
            'neck' => $height * 0.22 + $b * 0.45,
            'torso_length' => $height * 0.44,
            'bicep' => $height * 0.175 + $b * 0.75,
            'leg_length' => $height * 0.58,
            'pants_waist' => $height * 0.47 + $b * 2.9 + $a * 0.08 - 3,
            'thigh' => $height * 0.31 + $b * 1.1,
            'rise' => $height * 0.37 + $b * 0.45,
        ];

        return array_map(fn (float $v) => round($v * 2) / 2, $raw);
    }
}
