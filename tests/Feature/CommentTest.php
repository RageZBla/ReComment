<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Repository\CommentContract;
use Illuminate\Redis\RedisManager;
use Tests\CleanDatabase;

class CommentTest extends TestCase
{
    use CleanDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->createApplication();
        $this->cleanDatabase($app->get('redis'));
    }

    /**
     * @test
     * @dataProvider forbiddenProvider
     */
    public function it_is_accessible_to_only_users_logged_in(string $url, string $method): void
    {
        $response = $this->call($method, $url);
        $response->assertUnauthorized();
    }

    public function forbiddenProvider(): \Generator
    {
        yield ['/post', 'POST'];
        yield ['/delete', 'DELETE'];
        yield ['/like', 'POST'];
    }

    /**
     * @test
     * @dataProvider invalidCommentProvider
     */
    public function it_validates_post_content(array $parameters): void
    {
        $response = $this->loggedInRequest('POST', '/post', $parameters);

        $response->assertSessionHasErrors('comment');
        $response->assertStatus(302);
    }

    public function invalidCommentProvider(): \Generator
    {
        yield [[]];
        yield [['comment' => null]];
        yield [['comment' => '']];
        yield [['foo' => 'bar']];
    }

    /**
     * @test
     */
    public function post_comment(): void
    {
        $response = $this->loggedInRequest('POST', '/post', ['comment' => 'Foo bar']);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
        $response->assertRedirect('/');
    }

    /**
     * @test
     * @dataProvider invalidDeleteProvider
     */
    public function it_validates_delete_request_parameters(array $parameters): void
    {
        $response = $this->loggedInRequest('DELETE', '/delete', $parameters);

        $response->assertSessionHasErrors('comment_id');
        $response->assertStatus(302);
        $response->assertRedirect('/');
    }

    public function invalidDeleteProvider(): \Generator
    {
        yield [[]];
        yield [['comment_id' => null]];
        yield [['comment_id' => '']];
        yield [['foo' => 'bar']];
    }

    /**
     * @test
     */
    public function it_deletes_comment(): void
    {
        $app = $this->createApplication();
        /** @var RedisManager $redis */
        $redis = $app->get(RedisManager::class);
        /** @var CommentContract $commentRepository */
        $commentRepository = $app->get(CommentContract::class);
        $commentId = $commentRepository->post('foo', 1, 'jane');

        $response = $this->loggedInRequest('DELETE', '/delete', ['comment_id' => $commentId]);
        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
        $response->assertRedirect('/');
        $this->assertSame(0, $redis->exists('comment:' . $commentId));
    }

    /**
     * @test
     */
    public function it_forbids_deleting_other_people_comments(): void
    {
        $app = $this->createApplication();
        /** @var RedisManager $redis */
        $redis = $app->get(RedisManager::class);
        /** @var CommentContract $commentRepository */
        $commentRepository = $app->get(CommentContract::class);
        $commentId = $commentRepository->post('foo', 2, 'john');

        $response = $this->loggedInRequest('DELETE', '/delete', ['comment_id' => $commentId]);
        $response->assertForbidden();
        $this->assertSame(1, $redis->exists('comment:' . $commentId));
    }

    /**
     * @test
     * @dataProvider invalidLikesProvider
     */
    public function it_validates_likes_request_parameters(array $parameters): void
    {
        $response = $this->loggedInRequest('POST', '/like', $parameters);

        $response->assertSessionHasErrors('comment_id');
        $response->assertStatus(302);
        $response->assertRedirect('/');
    }

    public function invalidLikesProvider(): \Generator
    {
        yield [[]];
        yield [['comment_id' => null]];
        yield [['comment_id' => '']];
        yield [['foo' => 'bar']];
    }

    /**
     * @test
     */
    public function like_comment(): void
    {
        $app = $this->createApplication();
        /** @var RedisManager $redis */
        $redis = $app->get(RedisManager::class);
        /** @var CommentContract $commentRepository */
        $commentRepository = $app->get(CommentContract::class);
        $commentId = $commentRepository->post('foo', 2, 'john');

        $response = $this->loggedInRequest('POST', '/like', ['comment_id' => $commentId]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/');
        $this->assertSame(['1'], $redis->hmget('comment:' . $commentId, ['likes']));
    }
}
