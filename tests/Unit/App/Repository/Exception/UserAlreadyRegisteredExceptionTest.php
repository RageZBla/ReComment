<?php
declare(strict_types=1);

namespace Tests\Unit\App\Repository\Exception;

use App\Repository\Exception\UserAlreadyRegisteredException;

class UserAlreadyRegisteredExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_an_application_exception(): void
    {
        $this->assertIsAppException(new UserAlreadyRegisteredException('john'));
    }

    /**
     * @test
     */
    public function it_is_a_runtime_exception(): void
    {
        $this->assertRuntimeException(new UserAlreadyRegisteredException('john'));
    }

    /**
     * @test
     */
    public function its_message(): void
    {
        $this->assertSame(
            '"john" is already registered',
            (new UserAlreadyRegisteredException('john'))->getMessage()
        );
    }
}
