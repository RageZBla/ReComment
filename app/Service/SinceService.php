<?php
declare(strict_types=1);

namespace App\Service;


use Carbon\Carbon;

class SinceService
{
    public function since(int $t): string
    {
        $d = (int)Carbon::now()->format('U') - $t;
        if ($d < 60) {
            return "$d second" . ($d > 1 ? 's' : '');
        }
        if ($d < 3600) {
            $m = (int)($d / 60);
            return "$m minute" . ($m > 1 ? 's' : '');
        }
        if ($d < 3600 * 24) {
            $h = (int)($d / 3600);
            return "$h hour" . ($h > 1 ? 's' : '');
        }
        $d = (int)($d / (3600 * 24));
        return "$d day" . ($d > 1 ? 's' : '');
    }
}
