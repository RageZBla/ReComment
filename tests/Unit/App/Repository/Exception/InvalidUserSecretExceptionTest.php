<?php
declare(strict_types=1);

namespace Tests\Unit\App\Repository\Exception;

use App\Repository\Exception\InvalidUserSecretException;

class InvalidUserSecretExceptionTest extends TestCase
{

    /**
     * @test
     */
    public function it_is_an_app_exception(): void
    {
        $this->assertIsAppException(new InvalidUserSecretException());
    }

    /**
     * @test
     */
    public function it_is_an_invalid_argument_exception(): void
    {
        $this->assertInstanceOf(\InvalidArgumentException::class, new InvalidUserSecretException());
    }

    /**
     * @test
     */
    public function its_message(): void
    {
        $this->assertSame(
            'Invalid secret provided',
            (new InvalidUserSecretException())->getMessage()
        );
    }
}
