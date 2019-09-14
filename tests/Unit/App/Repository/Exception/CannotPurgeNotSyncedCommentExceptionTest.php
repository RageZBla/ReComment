<?php
declare(strict_types=1);

namespace Tests\Unit\App\Repository\Exception;

use App\Repository\Exception\CannotPurgeNotSyncedCommentException;

class CannotPurgeNotSyncedCommentExceptionTest extends TestCase
{

    /**
     * @test
     */
    public function it_is_an_application_exception(): void
    {
        $this->assertIsAppException(new CannotPurgeNotSyncedCommentException(1));
    }

    /**
     * @test
     */
    public function it_is_a_runtime_exception(): void
    {
        $this->assertLogicException(new CannotPurgeNotSyncedCommentException(1));
    }

    /**
     * @test
     */
    public function its_message(): void
    {
        $this->assertSame(
            'comment with ID: 1000 can not be purged because it has not been synced yet',
            (new CannotPurgeNotSyncedCommentException(1000))->getMessage()
        );
    }
}
