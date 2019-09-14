<?php
declare(strict_types=1);

namespace App\Service;


use App\Comment;
use App\Repository\CommentContract;
use App\Repository\UserContract;
use App\User;
use Carbon\Carbon;

class MysqlCacheSyncService implements CacheSyncContract
{
    /**
     * @var UserContract
     */
    private $userRepository;
    /**
     * @var CommentContract
     */
    private $commentRepository;

    public function __construct(UserContract $userRepository, CommentContract $commentRepository)
    {
        $this->userRepository = $userRepository;
        $this->commentRepository = $commentRepository;
    }

    /**
     * {@inheritDoc}
     */
    public function sync(int $numberMinutes): void
    {
        $older = Carbon::now()->subMinutes($numberMinutes);

        $this->syncUsers($older);
        $this->syncComments($older);
    }

    private function syncComments(\DateTimeInterface $older): void
    {
        $comments = $this->commentRepository->fetchUpdatedBetween(null, $older);
        foreach ($comments as $comment) {
            $id = $comment['id'];
            if ($this->commentRepository->isSynced($id)) {
                continue;
            }
            $commentModel = $this->createCommentModel($id, $comment);
            $commentModel->save();
            if ($commentModel->likes()) {
                $this->syncCommentLikes($commentModel);
            }
            $this->commentRepository->markAsSynced($id);
        }

        $deletedIds = $this->commentRepository->fetchDeletedIds();
        Comment::destroy($deletedIds);
        foreach ($deletedIds as $id) {
            $this->commentRepository->removeDeletedFlag($id);
        }
    }

    private function syncUsers(\DateTimeInterface $older): void
    {
        $users = $this->userRepository->fetchUpdatedBetween(null, $older);
        foreach ($users as $user) {
            $id = $user['id'];
            if ($this->userRepository->isSynced($id)) {
                continue;
            }
            $userModel = $this->createUserModel($id, $user);
            $userModel->save();
            $this->userRepository->markAsSynced($id);
        }
        $deletedIds = $this->userRepository->fetchDeletedIds();
        User::destroy($deletedIds);
        foreach ($deletedIds as $id) {
            $this->userRepository->removeDeletedFlag($id);
        }
    }

    private function createCommentModel(int $commentId, array $data): Comment
    {
        /** @var Comment $model */
        $model = Comment::firstOrNew(['id' => $commentId]);
        $model->id = $commentId;
        $model->comment = $data['comment'];
        $model->user_id = $data['user_id'];
        $model->username = $data['username'];
        $model->number_likes = $data['likes'];
        if ($model->created_at) {
            $model->touch();
        } else {
            $model->setUpdatedAt($data['timestamp']);
            $model->setCreatedAt($data['timestamp']);
        }

        return $model;
    }

    private function createUserModel(int $userId, array $data): User
    {
        /** @var User $model */
        $model = User::firstOrNew(['id' => $userId]);
        $model->id = $userId;
        $model->username = $data['username'];
        $model->auth = $data['auth'];
        if ($model->created_at) {
            $model->touch();
        } else {
            $model->setUpdatedAt($data['timestamp']);
            $model->setCreatedAt($data['timestamp']);
        }

        return $model;
    }

    private function syncCommentLikes(Comment $commentModel): void
    {
        $commentId = $commentModel->id;
        $userIds = $this->commentRepository->likedBy($commentId);

        // We are not using eloquent because some of users might not be synced yet...
        foreach ($userIds as $userId) {
            \DB::table('comment_like')->insertOrIgnore(['user_id' => $userId, 'comment_id' => $commentId]);
        }
    }
}
