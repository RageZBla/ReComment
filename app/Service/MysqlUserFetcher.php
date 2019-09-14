<?php
declare(strict_types=1);

namespace App\Service;


use App\Repository\UserFetcherContract;
use App\User;

class MysqlUserFetcher implements UserFetcherContract
{
    public function __invoke(int $userId): ?array
    {
        /** @var User $user */
        $user = User::find($userId);
        if (null === $user) {
            return null;
        }

        $data = $user->toArray();
        $data['timestamp'] = (new \DateTimeImmutable($data['created_at']))->format('U');
        unset($data['created_at'], $data['updated_at']);

        return $data;
    }
}
