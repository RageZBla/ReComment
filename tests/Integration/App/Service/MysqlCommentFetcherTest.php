<?php
declare(strict_types=1);

namespace Tests\Integration\App\Service;

use App\Comment;
use App\Service\MysqlCommentFetcher;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MysqlCommentFetcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->createApplication();
        DB::table('comments')->truncate();
        DB::table('users')->truncate();
        DB::table('comment_like')->truncate();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(null);
    }

    /**
     * @test
     */
    public function it_fetches_comment_data_using_eloquent()
    {
        $now = Carbon::now();
        Carbon::setTestNow($now);

        $comment = Comment::make(['comment' => 'Lorem Ipsum']);
        $comment->user_id = 1;
        $comment->username = 'john';
        $comment->save();

        $fetcher = new MysqlCommentFetcher();
        $data = $fetcher($comment->id);
        $this->assertArrayNotHasKey('created_at', $data);
        $this->assertArrayNotHasKey('updated_at', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('comment', $data);
        $this->assertArrayHasKey('user_id', $data);
        $this->assertArrayHasKey('username', $data);
        $this->assertArrayHasKey('likes', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertSame($now->format('U'), $data['timestamp']);

        // Null case
        $this->assertNull($fetcher(42));
    }

    /**
     * @test
     */
    public function it_fetches_comment_likes_data_using_eloquent()
    {
        $now = Carbon::now();
        Carbon::setTestNow($now);

        $user = User::make(['username' => 'john']);
        $user->auth = 'secret';
        $user->save();
        /** @var Comment $comment */
        $comment = Comment::make(['comment' => 'Lorem Ipsum']);
        $comment->user_id = 1;
        $comment->username = 'john';
        $comment->save();
        $comment->likes()->attach($user);

        $fetcher = new MysqlCommentFetcher();
        $this->assertSame([$user->id], $fetcher->likedBy($comment->id));

        // Null case
        $this->assertSame([], $fetcher->likedBy(42));
    }
}
