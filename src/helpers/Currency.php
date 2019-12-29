<?php

namespace superbig\vipps\helpers;

class Currency
{
    /**
     * Round amount to ensure enough digits
     *
     * @param float $amount
     * @param int   $precision
     *
     * @return false|float
     */
    public static function round($amount, $precision = 2)
    {
        return \round($amount, $precision);
    }
}