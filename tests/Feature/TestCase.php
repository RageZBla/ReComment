<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Repository\UserContract;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Testing\TestResponse;
use Tests\TestCase as Base;

abstract class TestCase extends Base
{

    protected function loggedInRequest(string $method, string $uri, array $parameters): TestResponse
    {
        $app = $this->createApplication();

        $userRepository = $app->make(UserContract::class);
        $userRepository->addUser('jane', 'secret');

        /** @var Encrypter $encrypter */
        $encrypter = $app->get(Encrypter::class);
        $secret = $encrypter->encrypt('secret', false);

        return $this->call($method, $uri, $parameters, ['auth' => $secret], [], [], null);
    }
}
