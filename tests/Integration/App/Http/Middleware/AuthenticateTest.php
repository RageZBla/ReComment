<?php
declare(strict_types=1);

namespace Tests\Integration\App\Http\Middleware;

use App\Http\Middleware\Authenticate;
use App\Repository\UserContract;
use Illuminate\Http\Request;
use Illuminate\Redis\RedisManager;
use Illuminate\View\Factory;
use Tests\CleanDatabase;
use Tests\TestCase;

class AuthenticateTest extends TestCase
{
    use CleanDatabase;

    /**
     * @test
     * @dataProvider dataProvider
     */
    public function it_share_username_to_views_and_set_request_parameters($request, ?int $expectedUserId, ?string $expectedUsername): void
    {
        $this->loadFixtures();
        $app = $this->createApplication();
        /** @var Authenticate $middleware */
        $middleware = $app->get(Authenticate::class);
        /** @var Factory $view */
        $view = $app->get(Factory::class);

        $middleware->handle($request, function ($request) use ($expectedUserId, $expectedUsername, $view) {
            $this->assertSame($expectedUserId, $request['_user_id']);
            $this->assertSame($expectedUsername, $request['_username']);
            $this->assertArrayHasKey('username', $view->getShared());
            $this->assertSame($expectedUsername, $view->getShared()['username']);
        });
    }

    public function dataProvider(): \Generator
    {
        yield [new Request(), null, null];

        /** @var Request $authenticatedRequest */
        $authenticatedRequest = new Request();
        $authenticatedRequest->cookies->set('auth', 'secret');
        yield [$authenticatedRequest, 1, 'john'];
    }

    private function loadFixtures(): void
    {
        $app = $this->createApplication();
        $redis = $app->get(RedisManager::class);
        $this->cleanDatabase($redis);
        /** @var UserContract $userRepository */
        $userRepository = $app->get(UserContract::class);

        $userRepository->addUser('john', 'secret');
    }
}
