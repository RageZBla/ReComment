<?php
declare(strict_types=1);


namespace App\Repository;


use App\Repository\Exception\UserAlreadyRegisteredException;

interface UserContract
{
    public function isRegistered(string $username): bool;

    public function getUserID(string $username): int;

    public function getUserIdBySecret(string $secret): int;

    /**
     * @param int $userId
     *
     * @return string
     * @throws
     */
    public function getUsername(int $userId): string;

    /**
     * @param int $userId
     *
     * @return string
     * @throws AuthSecretNotSetException
     */
    public function getUserAuthSecret(int $userId): string;

    public function getNextId(): int;

    /**
     * @param string $username
     * @param string $authSecret
     *
     * @return int
     * @throws UserAlreadyRegisteredException
     */
    public function addUser(string $username, string $authSecret): int;

    public function fetchUpdatedBetween(?\DateTimeInterface $start, ?\DateTimeInterface $end): array;

    public function markAsSynced(int $userId): void;

    public function isSynced(int $userId): bool;

    public function delete(int $userId): void;

    public function markAsDeleted(int $userId): void;

    public function fetchDeletedIds(): array;

    public function removeDeletedFlag(int $userId): void;

    public function fetchStalledIds(\DateTimeInterface $dateTime): array;

    public function purge(int $userId): void;

    /**
     * @param UserFetcherContract $fetcher the closure would be passed userId and it should return data or null
     */
    public function setFallbackFetcher(UserFetcherContract $fetcher): void;
}
