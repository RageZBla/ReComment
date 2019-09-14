<?php
declare(strict_types=1);


namespace App\Repository;


interface CommentContract
{
    public function post(string $content, int $userId, string $username): int;

    public function fetchHomeComments(): array;

    public function find(int $commentId): ?array;

    public function delete(int $commentId): void;

    public function purge(int $commentId): void;

    public function fetchPage(int $offset, int $limit): array;

    public function like(int $commentId, int $userId): void;

    public function numberLikes(int $commentId): int;

    /**
     * @param int $commentId
     *
     * @return array list of user id
     */
    public function likedBy(int $commentId): array;

    public function fetchUpdatedBetween(?\DateTimeInterface $start, ?\DateTimeInterface $end): array;

    public function markAsSynced(int $commentId): void;

    public function isSynced(int $commentId): bool;

    public function fetchDeletedIds(): array;

    public function removeDeletedFlag(int $commentId): void;

    public function fetchStalledIds(\DateTimeInterface $dateTime): array;

    /**
     * @param CommentFetcherContract $fetcher the closure would be passed commentId and it should return data or null
     */
    public function setFallbackFetcher(CommentFetcherContract $fetcher): void;
}
