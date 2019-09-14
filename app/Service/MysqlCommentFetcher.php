<?php
declare(strict_types=1);

namespace App\Service;


use App\Comment;
use App\Repository\CommentFetcherContract;

class MysqlCommentFetcher implements CommentFetcherContract
{
    public function __invoke(int $commentId): ?array
    {
        /** @var Comment $comment */
        $comment = Comment::find($commentId);
        if (null === $comment) {
            return null;
        }

        $data = $comment->toArray();
        $data['timestamp'] = (new \DateTimeImmutable($data['created_at']))->format('U');
        $data['likes'] = $comment->number_likes;
        unset($data['created_at'], $data['updated_at'], $data['number_likes']);

        return $data;
    }

    public function likedBy(int $commentId): array
    {
        /** @var Comment $comment */
        $comment = Comment::find($commentId);
        if (null === $comment) {
            return [];
        }

        return $comment->likes()->pluck('user_id')->toArray();
    }
}
