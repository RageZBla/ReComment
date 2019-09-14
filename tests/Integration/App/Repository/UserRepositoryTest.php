<?php
declare(strict_types=1);

namespace Tests\Integration\App\Repository;

use App\Repository\Exception\CannotPurgeNotSyncedUserException;
use App\Repository\Exception\InvalidUserSecretException;
use App\Repository\Exception\UserAlreadyRegisteredException;
use App\Repository\Exception\UserNotFoundException;
use App\Repository\UserFetcherContract;
use App\Repository\UserRepository;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Redis\RedisManager;
use Tests\CleanDatabase;
use Tests\TestCase;

final class UserRepositoryTest extends TestCase
{
    use CleanDatabase;

    /**
     * @var UserRepository
     */
    private $repository;

    /**
     * @var RedisManager
     */
    private $redis;


    protected function setUp(): void
    {
        parent::setUp();

        $app = $this->createApplication();

        /** @var RedisManager $redis */
        $this->redis = $app->get('redis');
        $this->repository = new UserRepository($this->redis);

        $this->cleanDatabase($this->redis);
        // Fixture one john user with ID 1000 and secret secret
        $this->loadFixtures($this->redis);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(null);
    }


    /**
     * @param RedisManager $redis
     */
    private function loadFixtures(RedisManager $redis): void
    {
        $redis->set('next_user_id', 1000);
        $redis->hset('users', 'john', 1000);
        $redis->hmset('user:1000', ['username' => 'john', 'auth' => 'secret']);
        $redis->hset('auths', 'secret', 1000);
        $redis->zadd('users_update', ['1000' => (float)Carbon::now()->format('U')]);
        $redis->zadd('users_hit', ['1000' => (float)Carbon::now()->format('U')]);
    }

    /**
     * @test
     * @dataProvider isRegisteredProvider
     */
    public function it_checks_if_user_is_registered(string $username, bool $expected): void
    {
        $this->assertSame($expected, $this->repository->isRegistered($username));
    }

    public function isRegisteredProvider(): \Generator
    {
        yield ['john', true];
        yield ['jane', false];
        yield ['adam', false];
    }

    /**
     * @test
     * @dataProvider userIDProvider
     */
    public function it_fetches_user_ID_given_username(string $username, ?int $userId): void
    {
        $this->assertSame($userId, $this->repository->getUserID($username));
    }

    public function userIDProvider(): \Generator
    {
        yield ['john', 1000];
        yield ['jane', 0];
        yield ['adam', 0];
    }

    /**
     * @test
     * @dataProvider authSecretProvider
     */
    public function it_fetches_auth_secret(int $userId, ?string $expected): void
    {
        $this->assertSame($expected, $this->repository->getUserAuthSecret($userId));
    }

    public function authSecretProvider(): \Generator
    {
        yield [1000, 'secret'];
    }

    /**
     * @test
     * @dataProvider invalidUserAuthProvider
     */
    public function it_throws_exception_if_users_secret_cannot_be_found(int $userId): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage(sprintf('user with ID: %d not found', $userId));

        $this->repository->getUserAuthSecret($userId);
    }

    public function invalidUserAuthProvider(): \Generator
    {
        yield [1001];
        yield [1002];
    }

    /**
     * @test
     */
    public function it_generate_new_user_IDs(): void
    {
        $this->assertSame(1001, $this->repository->getNextId());
        $this->assertSame(1002, $this->repository->getNextId());
    }

    /**
     * @test
     */
    public function it_adds_new_user_to_database(): void
    {
        $now = Carbon::now();
        Carbon::setTestNow($now);
        $userId = $this->repository->addUser('jane', 'big-secret');

        $expected = [
            'jane',
            'big-secret',
        ];
        $this->assertSame(1001, $userId);
        $this->assertSame(2, $this->redis->hlen('users'));
        $this->assertSame(2, $this->redis->hlen('auths'));
        $this->assertSame($expected, $this->redis->hmget('user:1001', ['username', 'auth']));
        $this->assertSame(2, $this->redis->zcard('users_update'));
        $this->assertSame($now->format('U'), $this->redis->zscore('users_update', '1001'));
    }

    /**
     * @test
     */
    public function it_throws_exception_if_you_try_to_register_already_registered_username(): void
    {
        $this->expectException(UserAlreadyRegisteredException::class);
        $this->expectExceptionMessage('"john" is already registered');

        $this->repository->addUser('john', 'foo');
    }

    /**
     * @test
     * @dataProvider secretProvider
     */
    public function it_fetches_user_ID_given_secret(string $secret, int $userId): void
    {
        $this->assertSame($userId, $this->repository->getUserIdBySecret($secret));
    }

    public function secretProvider(): \Generator
    {
        yield ['secret', 1000];
    }

    /**
     * @test
     * @dataProvider invalidUserIdProvider
     */
    public function it_throws_exception_when_trying_to_get_user_id_with_invalid_secret(string $secret): void
    {
        $this->expectException(InvalidUserSecretException::class);
        $this->expectExceptionMessage('Invalid secret provided');

        $this->repository->getUserIdBySecret($secret);
    }

    public function invalidUserIdProvider(): \Generator
    {
        yield [2000];
        yield [2001];
    }

    /**
     * @test
     * @dataProvider usernameProvider
     */
    public function it_fetches_username(int $userId, string $expected): void
    {
        $this->assertSame($expected, $this->repository->getUsername($userId));
    }

    public function usernameProvider(): \Generator
    {
        yield [1000, 'john'];
    }

    /**
     * @test
     * @dataProvider invalidUserIdProvider
     */
    public function it_throws_exception_when_trying_to_get_username_with_invalid_ID(int $userId): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->repository->getUsername($userId);
    }

    /**
     * @test
     */
    public function it_fetches_users_updated_in_time_frame(): void
    {
        $now = Carbon::now();
        Carbon::setTestNow($now);
        $this->cleanDatabase($this->redis);

        foreach (range(0, 100) as $n) {
            $id = $n;
            $key = \implode(':', ['user', (string)$id]);
            $this->redis->hmset($key, ['username' => 'john_' . $n, 'auth' => 'secret']);
            $this->redis->zadd('users_update', [(string)$id => (float)Carbon::now()->addMinutes($n)->format('U')]);
        }

        // Get first 25
        $users = $this->repository->fetchUpdatedBetween(Carbon::now()->addMinutes(0), Carbon::now()->addMinutes(24));
        $this->assertCount(25, $users);
        $this->assertSame(0, $users[0]['id']);
        $this->assertSame(11, $users[11]['id']);
        $this->assertSame(24, $users[24]['id']);

        // Get 25 - 49
        $users = $this->repository->fetchUpdatedBetween(Carbon::now()->addMinutes(25), Carbon::now()->addMinutes(49));
        $this->assertCount(25, $users);
        $this->assertSame(25, $users[0]['id']);
        $this->assertSame(36, $users[11]['id']);
        $this->assertSame(49, $users[24]['id']);

        // Get 75 - 100
        $users = $this->repository->fetchUpdatedBetween(Carbon::now()->addMinutes(75), Carbon::now()->addMinutes(99));
        $this->assertCount(25, $users);
        $this->assertSame(75, $users[0]['id']);
        $this->assertSame(86, $users[11]['id']);
        $this->assertSame(99, $users[24]['id']);

        // Match none
        $this->assertCount(0, $this->repository->fetchUpdatedBetween(Carbon::yesterday(), Carbon::now()->subSecond()));
    }

    /**
     * @test
     */
    public function it_marks_users_as_synced(): void
    {
        $now = Carbon::now();
        Carbon::setTestNow($now);

        $this->repository->markAsSynced(1000);

        $this->assertTrue($this->repository->isSynced(1000));
        $this->assertFalse($this->repository->isSynced(1001));
        $this->assertFalse($this->repository->isSynced(1002));
        $this->assertSame(1, $this->redis->zcard('users_sync'));
        $this->assertSame($now->format('U'), $this->redis->zscore('users_sync', '1000'));
    }


    /**
     * @test
     */
    public function it_delete_user_from_database(): void
    {
        $this->repository->delete(1000);

        $this->assertSame(0, $this->redis->hlen('users'));
        $this->assertSame(0, $this->redis->hlen('auth'));
        $this->assertSame(0, $this->redis->exists('user:1000'));
    }

    /**
     * @test
     */
    public function deleting_comment_also_remove_update_set(): void
    {
        $this->assertSame(1, $this->redis->zcard('users_update'));

        $this->repository->delete(1000);

        $this->assertSame(0, $this->redis->zcard('users_update'));
    }

    /**
     * @test
     */
    public function deleting_comment_also_remove_hit_set(): void
    {
        $this->assertSame(1, $this->redis->zcard('users_hit'));

        $this->repository->delete(1000);

        $this->assertSame(0, $this->redis->zcard('users_hit'));
    }

    /**
     * @test
     * @dataProvider invalidDeleteUserProvider
     */
    public function it_throws_exception_if_you_tried_to_delete_not_found_user(int $userId): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage(sprintf('user with ID: %d not found', $userId));

        $this->repository->delete($userId);
    }

    /**
     * @test
     */
    public function it_manage_a_list_of_IDs_that_should_be_removed_from_RDBM(): void
    {
        $now = CarbonImmutable::now();
        $yesterday = CarbonImmutable::yesterday();
        Carbon::setTestNow($yesterday);

        $userId = $this->repository->addUser('ragez', 'secret');
        $this->repository->markAsSynced($userId);

        Carbon::setTestNow($now);
        $notSyncedId = $this->repository->addUser('not_yet_synced', 'secret');

        $this->repository->delete($userId);
        $this->repository->delete($notSyncedId);

        $ids = $this->repository->fetchDeletedIds();
        $this->assertCount(1, $ids);
        $this->assertSame([1001], $ids);

        $this->repository->removeDeletedFlag($userId);
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

        $userId = $this->repository->addUser('ragez', 'secret');

        Carbon::setTestNow($now);
        $notSyncedId = $this->repository->addUser('new_user', 'secret');

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
    public function it_purges_user(): void
    {
        $this->cleanDatabase($this->redis);
        $userId = $this->repository->addUser('jane', 'secret');
        $this->repository->markAsSynced($userId);

        $this->repository->purge($userId);

        $this->assertSame(1, $this->redis->hlen('users'));
        $this->assertSame(0, $this->redis->exists('user:' . $userId));
        $this->assertSame(1, $this->redis->hlen('auths'));
        $this->assertSame(0, $this->redis->zcard('users_update'));
        $this->assertSame(0, $this->redis->zcard('users_hit'));
        $this->assertSame(0, $this->redis->zcard('users_deleted'));
    }

    /**
     * @test
     */
    public function it_throws_exception_if_you_try_to_purge_not_synced_user(): void
    {
        $userId = $this->repository->addUser('jane', 'secret');
        $this->expectException(CannotPurgeNotSyncedUserException::class);
        $this->expectExceptionMessage(
            sprintf('user with ID: %d can not be purged because it has not been synced yet', $userId)
        );

        $this->repository->purge($userId);
    }

    public function invalidDeleteUserProvider(): \Generator
    {
        yield [1001];
        yield [1002];
    }

    /**
     * @test
     */
    public function it_can_use_a_fallback_storage_if_data_has_been_purged(): void
    {
        $now = Carbon::now();
        Carbon::setTestNow($now);
        $this->cleanDatabase($this->redis);


        $fallbackFetcher = new class implements UserFetcherContract {
            public function __invoke(int $userId): ?array
            {
                return [
                    'username' => 'john',
                    'auth' => 'secret',
                    'timestamp' => (string)Carbon::now()->format('U'),
                ];
            }
        };

        $this->repository->setFallbackFetcher($fallbackFetcher);

        $this->assertSame('john', $this->repository->getUsername(1));
        $this->assertSame(1, $this->repository->getUserIdBySecret('secret'));
        $this->assertTrue($this->repository->isRegistered('john'));
    }

    /**
     * @test
     */
    public function it_throws_comment_not_found_exception_if_both_cache_and_fallback_do_not_have_content(): void
    {

        $this->cleanDatabase($this->redis);

        $fallbackFetcher = new class implements UserFetcherContract {
            public function __invoke(int $commentId): ?array
            {
                return null;
            }
        };

        $this->expectException(UserNotFoundException::class);
        $this->repository->setFallbackFetcher($fallbackFetcher);

        $this->repository->getUsername(1);
    }
}
