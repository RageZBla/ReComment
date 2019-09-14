<?php
declare(strict_types=1);


namespace App\Repository;


interface UserFetcherContract
{
    public function __invoke(int $userId): ?array;
}
