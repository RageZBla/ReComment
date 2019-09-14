<?php
declare(strict_types=1);

namespace App\Repository;


use App\Repository\Exception\InvalidUserSecretException;
use Carbon\Carbon;
use Illuminate\Redis\RedisManager;

final class UserRepository implements UserContract
{
    private const USERS = 'users';
    private const USERS_UPDATE = 'users_update';
    private const USERS_SYNC = 'users_sync';
    private const USERS_HIT = 'users_hit';
    private const USERS_DELETED = 'users_deleted';
    private const USER_SEQUENCE_KEY = 'next_user_id';
    private const AUTHS = 'auths';

    /**
     * @var RedisManager
     */
    private $redis;

    /**
     * @var UserFetcherContract|null
     */
    private $fallbackFetcher;

    public function __construct(RedisManager $redis, ?UserFetcherContract $fallbackFetcher = null)
    {
        $this->redis = $redis;
        $this->fallbackFetcher = $fallbackFetcher;
    }

    public function isRegistered(string $username): bool
    {
        return (bool)$this->getUserId($username);
    }

    public function getUserID(string $username): int
    {
        return (int)$this->redis->hget(self::USERS, $username);
    }

    public function getUserAuthSecret(int $userId): string
    {
        $user = $this->find($userId);

        return $user['auth'];
    }

    public function getNextId(): int
    {
        return $this->redis->incr(self::USER_SEQUENCE_KEY);
    }

    public function addUser(string $username, string $authSecret): int
    {
        if ($this->isRegistered($username)) {
            throw new Exception\UserAlreadyRegisteredException($username);
        }

        $userId = $this->getNextId();
        $timestamp = (float)Carbon::now()->format('U');
        $data = [
            'username' => $username,
            'auth' => $authSecret,
            'timestamp' => (int)$timestamp,
        ];

        $this->save($userId, $data);

        return $userId;
    }

    private function getUserKey(int $userId): string
    {
        return \implode(':', ['user', (string)$userId]);
    }

    public function getUserIdBySecret(string $secret): int
    {
        $userId = $this->redis->hget(self::AUTHS, $secret);
        if (null === $userId) {
            throw new InvalidUserSecretException();
        }

        return (int)$userId;
    }

    public function getUsername(int $userId): string
    {
        $user = $this->find($userId);

        return $user['username'];
    }

    public function fetchUpdatedBetween(?\DateTimeInterface $start, ?\DateTimeInterface $end): array
    {
        $convertToScore = function (\DateTimeInterface $d): float {
            return (float)$d->format('U');
        };
        if ($start) {
            $startScore = $convertToScore($start);
        } else {
            $startScore = '-inf';
        }
        if ($end) {
            $endScore = $convertToScore($end);
        } else {
            $endScore = '+inf';
        }

        $userIds = $this->redis->zrangebyscore(self::USERS_UPDATE, $startScore, $endScore);

        $users = [];
        foreach ($userIds as $userId) {
            $users[] = $this->find((int)$userId);
        }

        return $users;
    }

    private function find(int $userId): array
    {
        $key = $this->getUserKey($userId);

        if (0 === $this->redis->exists($key)) {
            if ($this->fallbackFetcher && $data = $this->fallbackFetcher->__invoke($userId)) {
                $this->save($userId, $data);

                return $data;
            }
            throw new Exception\UserNotFoundException($userId);
        }
        $data = $this->redis->hgetall($key);
        $data['id'] = $userId;
        $this->recordHit($userId);

        return $data;
    }

    public function isSynced(int $userId): bool
    {
        return null !== $this->redis->zscore(self::USERS_SYNC, (string)$userId);
    }

    public function markAsSynced(int $userId): void
    {
        $this->redis->zadd(self::USERS_SYNC, [(string)$userId => (float)Carbon::now()->format('U')]);
    }

    private function fetchUsers(array $userIds): array
    {
        $users = [];
        foreach ($userIds as $userId) {
            $users[] = $this->find((int)$userId);
        }

        return $users;
    }

    public function delete(int $userId): void
    {
        $user = $this->find($userId);
        $username = $user['username'];
        $secret = $user['auth'];
        $key = $this->getUserKey($userId);

        if (1 === $this->redis->del([$key])) {
            $this->redis->hdel(self::USERS, [$username]);
            $this->redis->hdel(self::AUTHS, [$secret]);
            $this->redis->zrem(self::USERS_UPDATE, $userId);
            if ($this->isSynced($userId)) {
                $this->markAsDeleted($userId);
            }
            $this->removeHit($userId);
        }
    }

    public function markAsDeleted(int $userId): void
    {
        $this->redis->sadd(self::USERS_DELETED, [(string)$userId]);
    }

    public function removeDeletedFlag(int $userId): void
    {
        $this->redis->srem(self::USERS_DELETED, (string)$userId);
    }

    public function fetchDeletedIds(): array
    {
        return $this->cleanIds($this->redis->smembers(self::USERS_DELETED));
    }

    public function fetchStalledIds(\DateTimeInterface $dateTime): array
    {
        return $this->cleanIds($this->redis->zrangebyscore(self::USERS_HIT, '-inf', (float)$dateTime->format('U')));
    }

    private function recordHit(int $userId): void
    {
        $this->redis->zadd(self::USERS_HIT, [(string)$userId => Carbon::now()->format('U')]);
    }

    private function removeHit(int $userId): void
    {
        $this->redis->zrem(self::USERS_HIT, (string)$userId);
    }

    public function purge(int $userId): void
    {
        if (false === $this->isSynced($userId)) {
            throw new Exception\CannotPurgeNotSyncedUserException($userId);
        }
        $user = $this->find($userId);
        $username = $user['username'];
        $secret = $user['auth'];
        $key = $this->getUserKey($userId);

        if (1 === $this->redis->del([$key])) {
            $this->redis->zrem(self::USERS_UPDATE, $userId);
            $this->removeHit($userId);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setFallbackFetcher(UserFetcherContract $fallbackFetcher): void
    {
        $this->fallbackFetcher = $fallbackFetcher;
    }

    private function save(int $userId, array $data): void
    {
        $username = $data['username'];
        $authSecret = $data['auth'];
        $timestamp = $data['timestamp'] ?? Carbon::now()->format('U');
        unset($data['id']);

        $this->redis->hmset($this->getUserKey($userId), $data);
        $this->redis->hset(self::USERS, $username, $userId);
        $this->redis->hset(self::AUTHS, $authSecret, $userId);
        $this->redis->zadd(self::USERS_UPDATE, [(string)$userId => $timestamp]);
        $this->recordHit($userId);
    }

    private function cleanIds(array $rawIds): array
    {
        return \array_map('intval', $rawIds);
    }
}
