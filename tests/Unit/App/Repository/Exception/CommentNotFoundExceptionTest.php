<?php
declare(strict_types=1);

namespace Tests\Unit\App\Repository\Exception;

use App\Repository\Exception\CommentNotFoundException;

class CommentNotFoundExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_an_application_exception(): void
    {
        $this->assertIsAppException(new CommentNotFoundException(1));
    }

    /**
     * @test
     */
    public function it_is_a_runtime_exception(): void
    {
        $this->assertRuntimeException(new CommentNotFoundException(1));
    }

    /**
     * @test
     */
    public function its_message(): void
    {
        $this->assertSame(
            'comment with ID: 1000 not found',
            (new CommentNotFoundException(1000))->getMessage()
        );
    }
}
