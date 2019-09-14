<?php
declare(strict_types=1);

namespace App\Service;


use App\Repository\CommentContract;
use App\Repository\UserContract;
use Carbon\Carbon;

class CachePurgerService implements CachePurgerContract
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
     * Purge any objects that have not been accessed for N minutes
     *
     * @param int $numberMinutes
     */
    public function purge(int $numberMinutes): void
    {
        $dateTime = Carbon::now()->subMinutes($numberMinutes);

        $this->purgeComments($dateTime);
        $this->purgeUsers($dateTime);
    }

    public function purgeComments(\DateTimeInterface $dateTime): void
    {
        $ids = $this->commentRepository->fetchStalledIds($dateTime);
        foreach ($ids as $id) {
            if ($this->commentRepository->isSynced($id)) {
                $this->commentRepository->purge($id);
            }
        }
    }

    public function purgeUsers(\DateTimeInterface $dateTime): void
    {
        $ids = $this->userRepository->fetchStalledIds($dateTime);
        foreach ($ids as $id) {
            if ($this->userRepository->isSynced($id)) {
                $this->userRepository->purge($id);
            }
        }
    }
}
