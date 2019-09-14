<?php
declare(strict_types=1);

namespace Tests\Integration\App\Repository;

use App\Repository\CommentFetcherContract;
use App\Repository\CommentRepository;
use App\Repository\Exception\CannotPurgeNotSyncedCommentException;
use App\Repository\Exception\CommentNotFoundException;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Faker\Factory;
use Illuminate\Redis\RedisManager;
use Tests\CleanDatabase;
use Tests\TestCase;

class CommentRepositoryTest extends TestCase
{
    use CleanDatabase;

    /**
     * @var RedisManager
     */
    private $redis;

    /**
     * @var CommentRepository
     */
    private $repository;

    /**
     * @var \Carbon\Carbon
     */
    private $now;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->createApplication();
        $this->redis = $app->get('redis');
        $this->repository = new CommentRepository($this->redis);

        $this->cleanDatabase($this->redis);

        $this->now = Carbon::now();
        Carbon::setTestNow($this->now);
        $this->loadFixtures();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(null);
    }

    /**
     * @test
     */
    public function it_adds_comment_to_database(): void
    {
        $commentId = $this->repository->post('Lorem Ipsum', 1, 'otwell');

        $timestamp = $this->now->format('U');
        $expected = [
            'comment' => 'Lorem Ipsum',
            'user_id' => '1',
            'username' => 'otwell',
            'likes' => '0',
            'timestamp' => (string)$timestamp,
        ];

        $this->assertSame(1001, $commentId);
        $this->assertSame(4, $this->redis->llen('comments'));
        $this->assertSame('1001', $this->redis->rpop('comments'));
        $this->assertSame($expected, $this->redis->hgetall('comment:1001'));
        $this->assertSame(4, $this->redis->zcard('comments_update'));
        $this->assertSame(4, $this->redis->zcard('comments_hit'));
        $this->assertEquals($timestamp, $this->redis->zscore('comments_update', (string)$commentId));
    }

    /**
     * @test
     */
    public function it_fetch_comments_for_home_page(): void
    {
        $this->cleanDatabase($this->redis);
        $faker = Factory::create();
        $ids = [];
        foreach (range(1, 200) as $id) {
            $ids[] = $id;
            $key = \implode(':', ['comment', (string)$id]);

            $comment = [
                'comment' => $faker->paragraph,
                'user_id' => $faker->randomNumber,
                'username' => $faker->firstName,
                'timestamp' => Carbon::now()->addMinutes($id)->format('U'),
            ];
            $this->redis->hmset($key, $comment);
        }

        $this->redis->rpush('comments', $ids);

        $comments = $this->repository->fetchHomeComments();
        $this->assertCount(100, $comments);
        $firstComment = $comments[0];
        $this->assertArrayHasKey('id', $firstComment);
        $this->assertArrayHasKey('comment', $firstComment);
        $this->assertArrayHasKey('user_id', $firstComment);
        $this->assertArrayHasKey('username', $firstComment);
        $this->assertArrayHasKey('timestamp', $firstComment);
    }

    /**
     * @test
     */
    public function it_fetch_comments_per_page(): void
    {
        $singleComment = $this->repository->fetchPage(1, 1);
        $expectedSingleComment = [
            [
                'comment' => 'Redis is killing the database market',
                'user_id' => '2',
                'username' => 'antirez',
                'timestamp' => Carbon::now()->subHours(4)->format('U'),
                'id' => 2,
            ]
        ];
        $this->assertSame($expectedSingleComment, $singleComment);

        $allComments = $this->repository->fetchPage(0, 100);
        $this->assertCount(3, $allComments);
    }

    /**
     * @test
     */
    public function it_fetches_comment_given_ID(): void
    {
        $expected = [
            'comment' => 'Laravel rocks',
            'user_id' => '1',
            'username' => 'otwell',
            'timestamp' => Carbon::yesterday()->format('U'),
            'id' => 1,
        ];

        $actual = $this->repository->find(1);
        $this->assertSame($expected, $actual);
    }

    /**
     * @test
     * @dataProvider  invalidFindCommentIdProvider
     */
    public function it_throws_exception_if_you_try_to_fetch_comment_not_in_database(int $commentId): void
    {
        $this->expectException(CommentNotFoundException::class);
        $this->expectExceptionMessage(sprintf('comment with ID: %d not found', $commentId));

        $this->repository->find($commentId);
    }

    public function invalidFindCommentIdProvider(): \Generator
    {
        yield [42];
        yield [32];
    }

    /**
     * @test
     */
    public function it_delete_comment_from_database(): void
    {
        $this->repository->delete(2);

        $this->assertSame(2, $this->redis->llen('comments'));
        $this->assertSame(0, $this->redis->exists('comment:2'));
    }

    /**
     * @test
     */
    public function deleting_comment_also_remove_update_set(): void
    {
        $this->assertSame(3, $this->redis->zcard('comments_update'));

        $this->repository->delete(2);

        $this->assertSame(2, $this->redis->zcard('comments_update'));
    }

    /**
     * @test
     */
    public function deleting_comment_also_remove_hit_set(): void
    {
        $this->assertSame(3, $this->redis->zcard('comments_hit'));

        $this->repository->delete(2);

        $this->assertSame(2, $this->redis->zcard('comments_hit'));
    }

    /**
     * @test
     */
    public function it_save_likes_on_comment(): void
    {
        $this->repository->like(1, 42);

        $this->assertSame(1, $this->redis->sismember('comment_like:1', '42'));
        $this->assertSame(['1'], $this->redis->hmget('comment:1', ['likes']));
        $this->assertSame([42], $this->repository->likedBy(1));
    }

    /**
     * @test
     */
    public function same_user_liking_the_same_content_does_not_increase_the_like_counter(): void
    {
        $this->repository->like(1, 42);
        $this->repository->like(1, 42);

        $this->assertSame(['1'], $this->redis->hmget('comment:1', ['likes']));
    }

    /**
     * @test
     */
    public function it_gets_number_of_likes_for_comment(): void
    {
        $this->assertSame(0, $this->repository->numberLikes(1));
        $this->assertSame(2, $this->repository->numberLikes(3));
    }

    /**
     * @test
     */
    public function it_fetches_comments_that_have_been_updated_during_time_frame(): void
    {
        $oneWeekAgo = Carbon::now()->subWeek();
        $twoDaysAgo = Carbon::now()->subDays(2);
        $yesterday = Carbon::yesterday();
        $tomorrow = Carbon::tomorrow();
        $now = Carbon::now();
        $fiveHoursAgo = Carbon::now()->subHours(5);
        $threeHoursAgo = Carbon::now()->subHours(3);
        $thirtyMinutesAgo = Carbon::now()->subMinutes(30);
        $fiftyMinutesAgo = Carbon::now()->subMinutes(50);

        // Match Laravel rocks only
        $posts = $this->repository->fetchUpdatedBetween($yesterday, $fiveHoursAgo);
        $this->assertCount(1, $posts);
        $this->assertSame(1, $posts[0]['id']);

        // Match on redis
        $posts = $this->repository->fetchUpdatedBetween($fiveHoursAgo, $threeHoursAgo);
        $this->assertCount(1, $posts);
        $this->assertSame(2, $posts[0]['id']);

        // Match only 42
        $posts = $this->repository->fetchUpdatedBetween($fiftyMinutesAgo, $thirtyMinutesAgo);
        $this->assertCount(1, $posts);
        $this->assertSame(3, $posts[0]['id']);


        // Match everything
        $posts = $this->repository->fetchUpdatedBetween($oneWeekAgo, $now);
        $this->assertCount(3, $posts);
        $this->assertSame(1, $posts[0]['id']);
        $this->assertSame(2, $posts[1]['id']);
        $this->assertSame(3, $posts[2]['id']);

        // Match Otwell and Antirez
        $posts = $this->repository->fetchUpdatedBetween($yesterday, $threeHoursAgo);
        $this->assertCount(2, $posts);
        $this->assertSame(1, $posts[0]['id']);
        $this->assertSame(2, $posts[1]['id']);

        // Match Antirez and 42
        $posts = $this->repository->fetchUpdatedBetween($fiveHoursAgo, $thirtyMinutesAgo);
        $this->assertCount(2, $posts);
        $this->assertSame(2, $posts[0]['id']);
        $this->assertSame(3, $posts[1]['id']);

        // Not specifying end date
        $posts = $this->repository->fetchUpdatedBetween($fiveHoursAgo, null);
        $this->assertCount(2, $posts);
        $this->assertSame(2, $posts[0]['id']);
        $this->assertSame(3, $posts[1]['id']);

        // Not specifying start and end date
        $posts = $this->repository->fetchUpdatedBetween(null, null);
        $this->assertCount(3, $posts);
        $this->assertSame(1, $posts[0]['id']);
        $this->assertSame(2, $posts[1]['id']);
        $this->assertSame(3, $posts[2]['id']);


        // Match nothing
        $this->assertCount(0, $this->repository->fetchUpdatedBetween($oneWeekAgo, $twoDaysAgo));
        $this->assertCount(0, $this->repository->fetchUpdatedBetween($thirtyMinutesAgo, $now));
        $this->assertCount(0, $this->repository->fetchUpdatedBetween($now, $tomorrow));
    }

    /**
     * @test
     */
    public function it_marks_comment_as_synced(): void
    {
        $now = Carbon::now();
        Carbon::setTestNow($now);

        $this->repository->markAsSynced(1);
        $this->repository->markAsSynced(2);

        $this->assertTrue($this->repository->isSynced(1));
        $this->assertTrue($this->repository->isSynced(2));
        $this->assertFalse($this->repository->isSynced(3));
        $this->assertSame(2, $this->redis->zcard('comments_sync'));
        $this->assertSame($now->format('U'), $this->redis->zscore('comments_sync', '1'));
        $this->assertSame($now->format('U'), $this->redis->zscore('comments_sync', '2'));
    }

    /**
     * @test
     */
    public function it_manage_a_list_of_IDs_that_should_be_removed_from_RDBM(): void
    {
        $now = CarbonImmutable::now();
        $yesterday = CarbonImmutable::yesterday();
        Carbon::setTestNow($yesterday);

        $commentId = $this->repository->post('Foo', 1, 'otwell');
        $this->repository->markAsSynced($commentId);

        Carbon::setTestNow($now);
        $notSyncedId = $this->repository->post('not yet synced', 1, 'otwell');

        $this->repository->delete($commentId);
        $this->repository->delete($notSyncedId);

        $ids = $this->repository->fetchDeletedIds();
        $this->assertCount(1, $ids);
        $this->assertSame([1001], $ids);

        $this->repository->removeDeletedFlag($commentId);
        $this->assertCount(0, $this->repository->fetchDeletedIds());
    }

    /**
     * @test
     */
    public function it_manage_a_list_of_IDs_that_should_be_removed_from_cache(): void
    {
        $this->cleanDatabase($this->redis);
        $now = CarbonImmutable::now();
        $yesterday = CarbonImmutable::yesterday();
        Carbon::setTestNow($yesterday);

        $commentId = $this->repository->post('Foo', 1, 'otwell');

        Carbon::setTestNow($now);
        $notSyncedId = $this->repository->post('New stuff is hot', 1, 'otwell');

        $ids = $this->repository->fetchStalledIds($now);
        $this->assertCount(2, $ids);
        $this->assertSame([1, 2], $ids);

        $olderIds = $this->repository->fetchStalledIds(Carbon::now()->subMinutes(10));
        $this->assertCount(1, $olderIds);
        $this->assertSame([1], $olderIds);
    }

    /**
     * @test
     */
    public function it_purges_comment(): void
    {
        $commentId = $this->repository->post('Foo', 1, 'otwell');
        $this->repository->markAsSynced($commentId);

        $this->repository->purge($commentId);

        // It should still be in index
        $this->assertSame(4, $this->redis->llen('comments'));
        $this->assertSame(0, $this->redis->exists('comment:' . $commentId));
        $this->assertSame(3, $this->redis->zcard('comments_update'));
        $this->assertSame(3, $this->redis->zcard('comments_hit'));
        // It should not be marked as deleted
        $this->assertSame(0, $this->redis->scard('comments_deleted'));
    }

    /**
     * @test
     */
    public function it_throws_exception_if_you_try_to_purge_not_synced_comment(): void
    {
        $commentId = $this->repository->post('Foo', 1, 'otwell');
        $this->expectException(CannotPurgeNotSyncedCommentException::class);
        $this->expectExceptionMessage(
            sprintf('comment with ID: %d can not be purged because it has not been synced yet', $commentId)
        );

        $this->repository->purge($commentId);
    }

    /**
     * @test
     */
    public function it_can_use_a_fallback_storage_if_data_has_been_purged(): void
    {
        $this->cleanDatabase($this->redis);

        $fallbackFetcher = new class implements CommentFetcherContract
        {
            public function __invoke(int $commentId): ?array
            {
                return [
                    'comment' => 'from fallback',
                ];
            }

            public function likedBy(int $commentId): array
            {
                return [1];
            }
        };

        $this->repository->setFallbackFetcher($fallbackFetcher);

        $expected = ['comment' => 'from fallback'];
        $this->assertSame($expected, $this->repository->find(1));
        $this->assertCount(1, $this->repository->likedBy(1));
    }

    /**
     * @test
     */
    public function it_throws_comment_not_found_exception_if_both_cache_and_fallback_do_not_have_content(): void
    {

        $this->cleanDatabase($this->redis);

        $fallbackFetcher = new class implements CommentFetcherContract {
            public function __invoke(int $commentId): ?array
            {
                return null;
            }

            public function likedBy(int $commentId): array
            {
                return [];
            }
        };

        $this->expectException(CommentNotFoundException::class);
        $this->repository->setFallbackFetcher($fallbackFetcher);

        $this->repository->find(1);
    }

    private function loadFixtures(): void
    {
        $comments = [
            [
                'id' => 1,
                'comment' => 'Laravel rocks',
                'user_id' => 1,
                'username' => 'otwell',
                'timestamp' => Carbon::yesterday()->format('U'),
            ],
            [
                'id' => 2,
                'comment' => 'Redis is killing the database market',
                'user_id' => 2,
                'username' => 'antirez',
                'timestamp' => Carbon::now()->subHours(4)->format('U'),
            ],
            [
                'id' => 3,
                'comment' => 'The meaning of life and everything',
                'user_id' => 42,
                'username' => 'forty_two',
                'timestamp' => Carbon::now()->subMinutes(42)->format('U'),
            ],
        ];

        $this->redis->set('next_comment_id', 1000);

        foreach ($comments as $comment) {
            $id = $comment['id'];
            unset($comment['id']);
            $key = \implode(':', ['comment', (string)$id]);

            $this->redis->hmset($key, $comment);
            $this->redis->rpush('comments', [$id]);
            $this->redis->zadd('comments_update', [(string)$id => (float)$comment['timestamp']]);
            $this->redis->zadd('comments_hit', [(string)$id => (float)$comment['timestamp']]);
        }

        $likeKey = \implode(':', ['comment_like', (string)3]);
        $commentKey = \implode(':', ['comment', (string)3]);
        $this->redis->sadd($likeKey, [1, 2]);
        $this->redis->hset($commentKey, 'likes', 2);
    }
}
