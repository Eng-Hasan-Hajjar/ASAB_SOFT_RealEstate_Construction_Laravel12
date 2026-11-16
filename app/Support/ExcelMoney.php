<?php
namespace App\Support;

final class ExcelMoney
{
    public static function parse(mixed $raw): ?string
    {
        if ((!is_string($raw) && !is_int($raw) && !is_float($raw)) || $raw==='') return null;
        $value=str_replace([',',' ','٬'],'',trim((string)$raw));
        if (preg_match('/^([0-9]{1,15})(?:\.([0-9]{1,3}))?$/D',$value,$m)) {
            return (ltrim($m[1],'0')?:'0').'.'.str_pad($m[2]??'',3,'0');
        }
        // Accept only binary floating-point tail noise, not arbitrary amount rounding.
        if (!preg_match('/^\d{1,11}\.\d{4,20}$/D',$value)) return null;
        $number=(float)$value;
        $rounded=round($number,3);
        if (abs($number-$rounded)>min(0.000000001,max(0.000000000001,abs($number)*PHP_FLOAT_EPSILON*2))) return null;
        return number_format($rounded,3,'.','');
    }
}
