<?php
declare(strict_types=1);

namespace Tests\Integration\App\Service;

use App\Repository\CommentContract;
use App\Repository\UserContract;
use App\Service\CachePurgerService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Redis\RedisManager;
use Tests\CleanDatabase;
use Tests\TestCase;

class CachePurgerServiceTest extends TestCase
{

    use CleanDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->createApplication();
        $redis = $app->get(RedisManager::class);
        $this->cleanDatabase($redis);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(null);
    }

    /**
     * @test
     */
    public function it_purges_stalled_records_out_of_the_cache(): void
    {
        $now = CarbonImmutable::now();

        $app = $this->createApplication();
        /** @var RedisManager $redis */
        $redis = $app->get(RedisManager::class);
        /** @var UserContract $userRepository */
        $userRepository = $app->get(UserContract::class);
        /** @var CommentContract $commentRepository */
        $commentRepository = $app->get(CommentContract::class);

        $purgedCommentIds = [];
        $notPurgedCommentIds = [];
        $purgedUserIds = [];
        $notPurgedUserIds = [];
        Carbon::setTestNow($now->subMinutes(10));

        $userId = $userRepository->addUser('john', 'secret');
        $commentId = $commentRepository->post('Lorem Ipsum', $userId, 'john');
        $commentRepository->markAsSynced($commentId);
        $userRepository->markAsSynced($userId);
        $purgedCommentIds[] = $commentId;
        $purgedUserIds[] = $userId;

        // Too fresh
        Carbon::setTestNow($now->subMinutes(2));
        $userId = $userRepository->addUser('jane', 'secret');
        $commentId = $commentRepository->post('Lorem Ipsum', $userId, 'john');
        $commentRepository->markAsSynced($commentId);
        $userRepository->markAsSynced($userId);

        $notPurgedCommentIds[] = $commentId;
        $notPurgedUserIds[] = $userId;

        Carbon::setTestNow($now);

        $service = new CachePurgerService($userRepository, $commentRepository);

        $service->purge(10);

        // Comments
        foreach ($purgedCommentIds as $id) {
            $this->assertSame(0, $redis->exists('comment:' . $id));
        }
        foreach ($notPurgedCommentIds as $id) {
            $this->assertSame(1, $redis->exists('comment:' . $id));
        }

        // Users
        foreach ($purgedUserIds as $id) {
            $this->assertSame(0, $redis->exists('user:' . $id));
        }
        foreach ($notPurgedUserIds as $id) {
            $this->assertSame(1, $redis->exists('user:' . $id));
        }
    }

    /**
     * @test
     */
    public function it_would_not_try_to_purge_objects_that_have_not_been_synced(): void
    {
        $now = CarbonImmutable::now();

        $app = $this->createApplication();
        /** @var RedisManager $redis */
        $redis = $app->get(RedisManager::class);
        /** @var UserContract $userRepository */
        $userRepository = $app->get(UserContract::class);
        /** @var CommentContract $commentRepository */
        $commentRepository = $app->get(CommentContract::class);

        Carbon::setTestNow($now->subMinutes(10));

        $userId = $userRepository->addUser('john', 'secret');
        $commentId = $commentRepository->post('Lorem Ipsum', $userId, 'john');


        Carbon::setTestNow($now);

        $service = new CachePurgerService($userRepository, $commentRepository);

        $service->purge(10);

        $this->assertSame(1, $redis->exists('comment:' . $commentId));
        $this->assertSame(1, $redis->exists('user:' . $userId));
    }
}
