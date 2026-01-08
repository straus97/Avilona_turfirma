<?php

if (!function_exists('str_plural')) {
    /**
     * Склонение слов в зависимости от числа
     *
     * @param int $number Число
     * @param string $one Форма для 1 (1 день)
     * @param string $few Форма для 2-4 (2 дня)
     * @param string $many Форма для 5+ (5 дней)
     * @return string
     */
    function str_plural(int $number, string $one, string $few, string $many): string
    {
        $number = abs($number);
        $number %= 100;
        
        if ($number >= 11 && $number <= 19) {
            return $many;
        }
        
        $i = $number % 10;
        
        return match (true) {
            $i == 1 => $one,
            $i >= 2 && $i <= 4 => $few,
            default => $many,
        };
    }
}
