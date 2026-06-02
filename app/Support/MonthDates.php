<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class MonthDates
{
    /**
     * Maps a due date into a target year/month, keeping the day when valid
     * or clamping to the month's last day (e.g. May 31 → Jun 30).
     */
    public static function shiftToMonth(string $date, int $year, int $month): string
    {
        $parsed = Carbon::parse($date);
        $daysInTargetMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $day = min($parsed->day, $daysInTargetMonth);

        return Carbon::create($year, $month, $day)->format('Y-m-d');
    }
}
