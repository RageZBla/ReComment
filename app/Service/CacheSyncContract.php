<?php
declare(strict_types=1);


namespace App\Service;


interface CacheSyncContract
{
    /**
     * Sync all the object that have been on cache server for X minutes
     *
     * @param int $numberMinutes
     */
    public function sync(int $numberMinutes): void;
}
