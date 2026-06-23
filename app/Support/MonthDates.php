<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class MonthDates
{

    public static function shiftToMonth(string $date, int $year, int $month): string
    {
        $parsed = Carbon::parse($date);
        $daysInTargetMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $day = min($parsed->day, $daysInTargetMonth);

        return Carbon::create($year, $month, $day)->format('Y-m-d');
    }
}
