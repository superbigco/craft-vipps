<?php

namespace superbig\vipps\helpers;

use function round;

class Currency
{
    /**
     * Round amount to ensure enough digits
     *
     * @param float $amount
     * @param int $precision
     *
     * @return false|float
     */
    public static function round(float $amount, int $precision = 2): float|bool
    {
        return round($amount, $precision);
    }

    public static function roundAndConvertToMinorUnit($amount): int
    {
        return intval(round(floatval("{$amount}") * 100, 2));
    }

    public static function convertFromMinorUnit($amount): float|int
    {
        return $amount / 100;
    }
}
