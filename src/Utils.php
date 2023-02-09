<?php
declare(strict_types=1);
namespace Wwwision\ChildProcessPool;

final class Utils {

    public static function formatSeconds(int $seconds): string
    {
        $SEC_ARRAY = [
            60, // 60 seconds in 1 min
            60, // 60 minutes in 1 hour
            24, // 24 hours in 1 day
            7, // 7 days in 1 week
            365 / 7 / 12, // 4.345238095238096 weeks in 1 month
            12, // 12 months in 1 year
        ];

        $UNITS = ['second', 'minute', 'hour', 'day', 'week', 'month', 'year'];

        $diff = $seconds;
        for ($idx = 0; $idx < 6 && $diff >= $SEC_ARRAY[$idx]; $idx++) {
            $diff /= $SEC_ARRAY[$idx];
        }
        $diff = (int)floor($diff);
        $idx *= 2;
        if ($diff > ($idx === 0 ? 9 : 1)) {
            $idx ++;
        }
        return sprintf('%s %s%s', $diff, $UNITS[(int)floor($idx / 2)], $diff !== 1 ? 's' : '');
    }
}
