<?php

namespace App\Support;

final class CsvSafe
{
    /**
     * Prefix formula triggers so spreadsheet applications treat the value as text.
     *
     * Mitigates CSV injection (formula injection) when exporting user-controlled
     * data such as ticket subjects and resolutions.
     */
    public static function cell(mixed $value): string
    {
        $string = (string) $value;

        if ($string !== '' && in_array($string[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$string;
        }

        return $string;
    }
}
