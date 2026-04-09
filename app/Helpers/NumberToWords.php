<?php

namespace App\Helpers;

class NumberToWords
{
    public static function convert($number)
    {
        $whole = floor($number);
        $fraction = round(($number - $whole) * 100);

        $words = self::convertWholeNumber($whole) . ' Naira';

        if ($fraction > 0) {
            $words .= ' and ' . self::convertWholeNumber($fraction) . ' Kobo';
        }

        return $words . ' Only';
    }

    private static function convertWholeNumber($number)
    {
        // Add your number to words conversion logic here
        // This is a simplified version - consider using a package for full implementation
        if ($number < 1000) {
            return (string) $number;
        }

        return number_format($number);
    }
}