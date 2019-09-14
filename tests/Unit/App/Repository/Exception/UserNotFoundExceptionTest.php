<?php
declare(strict_types=1);

namespace Tests\Unit\App\Repository\Exception;

use App\Repository\Exception\UserNotFoundException;

class UserNotFoundExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_an_application_exception(): void
    {
        $this->assertIsAppException(new UserNotFoundException(1));
    }

    /**
     * @test
     */
    public function it_is_a_runtime_exception(): void
    {
        $this->assertRuntimeException(new UserNotFoundException(1));
    }

    /**
     * @test
     */
    public function its_message(): void
    {
        $this->assertSame(
            'user with ID: 1000 not found',
            (new UserNotFoundException(1000))->getMessage()
        );
    }
}
