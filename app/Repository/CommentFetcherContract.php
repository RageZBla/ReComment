<?php
declare(strict_types=1);


namespace App\Repository;


interface CommentFetcherContract
{
    public function __invoke(int $commentId): ?array;
    public function likedBy(int $commentId): array;
}
