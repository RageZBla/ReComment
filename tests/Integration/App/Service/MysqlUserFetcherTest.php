<?php
declare(strict_types=1);

namespace Tests\Integration\App\Service;

use App\Service\MysqlUserFetcher;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MysqlUserFetcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->createApplication();
        DB::table('users')->truncate();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(null);
    }

    /**
     * @test
     */
    public function it_fetches_user_data_using_eloquent()
    {
        $now = Carbon::now();
        Carbon::setTestNow($now);

        $user = User::make();
        $user->username = 'john';
        $user->auth = 'secret';
        $user->save();

        $fetcher = new MysqlUserFetcher();
        $data = $fetcher($user->id);
        $this->assertArrayNotHasKey('created_at', $data);
        $this->assertArrayNotHasKey('updated_at', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('username', $data);
        $this->assertArrayHasKey('auth', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertSame($now->format('U'), $data['timestamp']);

        // Null case
        $this->assertNull($fetcher(42));
    }
}
