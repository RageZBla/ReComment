<?php
declare(strict_types=1);

namespace Tests\Integration\App\Service;

use App\Comment;
use App\Repository\CommentContract;
use App\Repository\UserContract;
use App\Service\MysqlCacheSyncService;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\DB;
use Tests\CleanDatabase;
use Tests\TestCase;

class MysqlCacheSyncServiceTest extends TestCase
{
    use CleanDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->createApplication();
        $redis = $app->get(RedisManager::class);
        $this->cleanDatabase($redis);
        $tableNames = ['users', 'comments', 'comment_like'];
        foreach ($tableNames as $name) {
            DB::table($name)->truncate();
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(null);
    }


    /**
     * @test
     */
    public function it_syncs_comments_and_users_created_for_longer_than_5_minutes(): void
    {
        $now = CarbonImmutable::now();

        $app = $this->createApplication();
        /** @var UserContract $userRepository */
        $userRepository = $app->get(UserContract::class);
        /** @var CommentContract $commentRepository */
        $commentRepository = $app->get(CommentContract::class);

        $syncedCommentIds = [];
        $syncedUserIds = [];
        $notSyncedCommentIds = [];
        $notSyncedUserIds = [];
        Carbon::setTestNow($now->subMinutes(5));

        $userId = $userRepository->addUser('john', 'secret');
        $commentId = $commentRepository->post('Lorem Ipsum', $userId, 'john');

        $syncedCommentIds[] = $commentId;
        $syncedUserIds[] = $userId;

        // Too fresh
        Carbon::setTestNow($now->subMinutes(2));
        $userId = $userRepository->addUser('jane', 'secret');
        $commentId = $commentRepository->post('Lorem Ipsum', $userId, 'john');

        $notSyncedCommentIds[] = $commentId;
        $notSyncedUserIds[] = $userId;

        Carbon::setTestNow($now);

        $service = new MysqlCacheSyncService($userRepository, $commentRepository);

        $service->sync(5);

        // Users
        $this->assertSame(count($syncedUserIds), User::count());
        foreach ($syncedUserIds as $userId) {
            $this->assertTrue($userRepository->isSynced($userId));
            $user = User::find($userId);
            $this->assertInstanceOf(User::class, $user);
        }
        foreach ($notSyncedUserIds as $userId) {
            $this->assertFalse($userRepository->isSynced($userId));
        }

        // Comments
        $this->assertSame(count($syncedCommentIds), Comment::count());
        foreach ($syncedCommentIds as $commentId) {
            $this->assertTrue($commentRepository->isSynced($commentId));
            $comment = Comment::find($commentId);
            $this->assertInstanceOf(Comment::class, $comment);
        }
        foreach ($notSyncedCommentIds as $commentId) {
            $this->assertFalse($commentRepository->isSynced($commentId));
        }
    }


    /**
     * @test
     */
    public function it_does_not_sync_again_things_already_synced(): void
    {
        $app = $this->createApplication();
        /** @var UserContract $userRepository */
        $userRepository = $app->get(UserContract::class);
        /** @var CommentContract $commentRepository */
        $commentRepository = $app->get(CommentContract::class);

        $userId = $userRepository->addUser('john', 'secret');
        $commentId = $commentRepository->post('Lorem Ipsum', $userId, 'john');
        $userRepository->markAsSynced($userId);
        $commentRepository->markAsSynced($commentId);

        $service = new MysqlCacheSyncService($userRepository, $commentRepository);

        $service->sync(5);

        $this->assertSame(0, User::count());
        $this->assertSame(0, Comment::count());
    }

    /**
     * @test
     */
    public function it_removes_deleted_users_and_comments(): void
    {
        $now = CarbonImmutable::now();
        $app = $this->createApplication();
        /** @var RedisManager $redis */
        $redis = $app->get(RedisManager::class);
        /** @var UserContract $userRepository */
        $userRepository = $app->get(UserContract::class);
        /** @var CommentContract $commentRepository */
        $commentRepository = $app->get(CommentContract::class);

        Carbon::setTestNow($now->subMinutes(6));
        $userId = $userRepository->addUser('john', 'secret');
        $commentId = $commentRepository->post('Lorem Ipsum', $userId, 'john');

        $service = new MysqlCacheSyncService($userRepository, $commentRepository);

        Carbon::setTestNow($now);
        $service->sync(5);
        $this->assertSame(1, User::count());
        $this->assertSame(1, Comment::count());


        $userRepository->delete($userId);
        $commentRepository->delete($commentId);
        $this->assertSame(1, $redis->sismember('comments_deleted', (string)$commentId));
        $this->assertSame(1, $redis->sismember('users_deleted', (string)$userId));

        $service->sync(5);

        $this->assertSame(0, Comment::count());
        $this->assertSame(0, $redis->sismember('comments_deleted', (string)$commentId));
        $this->assertSame(0, User::count());
        $this->assertSame(0, $redis->sismember('users_deleted', (string)$userId));
    }

    /**
     * @test
     */
    public function it_syncs_comment_likes(): void
    {
        $now = CarbonImmutable::now();

        $app = $this->createApplication();
        /** @var UserContract $userRepository */
        $userRepository = $app->get(UserContract::class);
        /** @var CommentContract $commentRepository */
        $commentRepository = $app->get(CommentContract::class);

        Carbon::setTestNow($now->subMinutes(5));

        $userId = $userRepository->addUser('john', 'secret');
        $commentId = $commentRepository->post('Lorem Ipsum', $userId, 'john');
        $commentRepository->like($commentId, $userId); // Pretty narcissistic

        Carbon::setTestNow($now);

        $service = new MysqlCacheSyncService($userRepository, $commentRepository);

        $service->sync(5);

        /** @var Comment $actual */
        $actual = Comment::find($commentId);
        $this->assertSame(1, $actual->likes()->count());

    }
}
