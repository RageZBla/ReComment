<?php
declare(strict_types=1);

namespace App\Repository;


use Carbon\Carbon;
use Illuminate\Redis\RedisManager;

class CommentRepository implements CommentContract
{
    private const COMMENTS = 'comments';
    private const COMMENTS_UPDATE = 'comments_update';
    private const COMMENTS_HIT = 'comments_hit';
    private const COMMENTS_SYNC = 'comments_sync';
    private const SEQUENCE_KEY = 'next_comment_id';
    private const COMMENTS_DELETED = 'comments_deleted';
    const NUMBER_COMMENTS_ON_HOME = 100;

    /**
     * @var RedisManager
     */
    private $redis;

    /**
     * @var CommentFetcherContract|null
     */
    private $fallbackFetcher;

    public function __construct(RedisManager $redis, ?CommentFetcherContract $fallbackFetcher = null)
    {
        $this->redis = $redis;
        $this->fallbackFetcher = $fallbackFetcher;
    }


    public function post(string $comment, int $userId, string $username): int
    {
        $commentId = $this->getNextId();
        $timestamp = Carbon::now()->format('U');
        $data = [
            'comment'   => $comment,
            'user_id'   => $userId,
            'username'  => $username,
            'likes'     => 0,
            'timestamp' => $timestamp,
        ];

        $this->save($commentId, $data);
        $this->redis->rpush(self::COMMENTS, [$commentId]);

        return $commentId;
    }

    public function fetchHomeComments(): array
    {
        $commentIds = $this->redis->lrange(self::COMMENTS, 0, self::NUMBER_COMMENTS_ON_HOME - 1);

        return $this->fetchComments($this->cleanIds($commentIds));
    }

    public function fetchPage(int $offset, int $limit): array
    {
        $commentIds = $this->redis->lrange(self::COMMENTS, $offset, $offset + $limit - 1);

        return $this->fetchComments($this->cleanIds($commentIds));
    }

    public function find(int $commentId): array
    {
        $key = $this->getKey($commentId);

        if (0 === $this->redis->exists($key)) {
            if ($this->fallbackFetcher && $data = $this->fallbackFetcher->__invoke($commentId)) {
                $this->save($commentId, $data);

                $this->restoreLikes($commentId);

                return $data;
            }
            throw new Exception\CommentNotFoundException($commentId);
        }
        $data = $this->redis->hgetall($key);
        $data['id'] = $commentId;
        $this->recordHit($commentId);

        return $data;
    }

    public function delete(int $commentId): void
    {
        $key = $this->getKey($commentId);

        if (1 === $this->redis->del([$key])) {
            $this->redis->lrem(self::COMMENTS, 0, $commentId);
            $this->redis->zrem(self::COMMENTS_UPDATE, $commentId);
            if ($this->isSynced($commentId)) {
                $this->markAsDeleted($commentId);
            }
            $this->removeHit($commentId);
        }
    }

    public function like(int $commentId, int $userId): void
    {
        $key = $this->getLikeKey($commentId);

        if ($this->redis->sadd($key, [$userId])) {
            $this->redis->hincrby($this->getKey($commentId), 'likes', 1);
            $this->recordHit($commentId);
        }
    }

    public function numberLikes(int $commentId): int
    {
        $key = $this->getLikeKey($commentId);

        return $this->redis->scard($key);
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

        $commentIds = $this->redis->zrangebyscore(self::COMMENTS_UPDATE, $startScore, $endScore);

        return $this->fetchComments($this->cleanIds($commentIds));
    }

    private function getNextId(): int
    {
        return $this->redis->incr(self::SEQUENCE_KEY);
    }

    private function getKey(int $commentId): string
    {
        return \implode(':', ['comment', (string)$commentId]);
    }

    private function getLikeKey(int $commentId): string
    {
        return \implode(':', ['comment_like', (string)$commentId]);
    }

    public function markAsSynced(int $commentId): void
    {
        $this->redis->zadd(self::COMMENTS_SYNC, [(string)$commentId => (float)Carbon::now()->format('U')]);
    }

    /**
     * @param array $commentIds
     *
     * @return array
     */
    protected function fetchComments(array $commentIds): array
    {
        $comments = [];
        foreach ($commentIds as $commentId) {
            $comments[] = $this->find((int)$commentId);
        }

        return $comments;
    }

    public function isSynced(int $commentId): bool
    {
        return null !== $this->redis->zscore(self::COMMENTS_SYNC, (string)$commentId);
    }

    private function markAsDeleted(int $commentId): void
    {
        $this->redis->sadd(self::COMMENTS_DELETED, [(string)$commentId]);
    }

    public function removeDeletedFlag(int $commentId): void
    {
        $this->redis->srem(self::COMMENTS_DELETED, (string)$commentId);
    }

    public function fetchDeletedIds(): array
    {
        return $this->cleanIds($this->redis->smembers(self::COMMENTS_DELETED));
    }

    public function fetchStalledIds(\DateTimeInterface $dateTime): array
    {
        $rawIds = $this->redis->zrangebyscore(self::COMMENTS_HIT, '-inf', (float)$dateTime->format('U'));

        return $this->cleanIds($rawIds);
    }

    private function recordHit(int $commentId): void
    {
        $this->redis->zadd(self::COMMENTS_HIT, [(string)$commentId => Carbon::now()->format('U')]);
    }

    private function removeHit(int $commentId): void
    {
        $this->redis->zrem(self::COMMENTS_HIT, (string)$commentId);
    }

    public function purge(int $commentId): void
    {
        if (false === $this->isSynced($commentId)) {
            throw new Exception\CannotPurgeNotSyncedCommentException($commentId);
        }
        $key = $this->getKey($commentId);

        if (1 === $this->redis->del([$key])) {
            $this->redis->zrem(self::COMMENTS_UPDATE, $commentId);
            $this->removeHit($commentId);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setFallbackFetcher(CommentFetcherContract $fetcher): void
    {
        $this->fallbackFetcher = $fetcher;
    }

    private function save(int $commentId, array $data): void
    {
        $commentKey = $this->getKey($commentId);
        $timestamp = $data['timestamp'] ?? Carbon::now()->format('U');
        unset($data['id']);

        $this->redis->hmset($commentKey, $data);

        $this->redis->zadd(self::COMMENTS_UPDATE, [(string)$commentId => $timestamp]);
        $this->recordHit($commentId);
    }

    private function cleanIds(array $rawIds): array
    {
        return array_map('intval', $rawIds);
    }

    public function likedBy(int $commentId): array
    {
        $key = $this->getLikeKey($commentId);

        return $this->cleanIds($this->redis->smembers($key));
    }

    private function restoreLikes(int $commentId): void
    {
        $userIds = $this->fallbackFetcher->likedBy($commentId);

        $key = $this->getLikeKey($commentId);
        foreach ($userIds as $userId) {
            $this->redis->sadd($key, $userId);
        }
    }
}
