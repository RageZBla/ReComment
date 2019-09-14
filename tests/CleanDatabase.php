<?php
declare(strict_types=1);


namespace Tests;


use Illuminate\Redis\RedisManager;

/**
 * Trait CleanDatabase
 *
 * @package Tests\Integration\App\Repository
 */
trait CleanDatabase
{
    private function cleanDatabase(RedisManager $redis): void
    {
        $redis->flushDb();
    }
}
