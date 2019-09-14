<?php
declare(strict_types=1);

namespace Tests\Unit\App\Repository\Exception;

use App\Repository\Exception\CannotPurgeNotSyncedUserException;

class CannotPurgeNotSyncedUserExceptionTest extends TestCase
{

    /**
     * @test
     */
    public function it_is_an_application_exception(): void
    {
        $this->assertIsAppException(new CannotPurgeNotSyncedUserException(1));
    }

    /**
     * @test
     */
    public function it_is_a_runtime_exception(): void
    {
        $this->assertLogicException(new CannotPurgeNotSyncedUserException(1));
    }

    /**
     * @test
     */
    public function its_message(): void
    {
        $this->assertSame(
            'user with ID: 1000 can not be purged because it has not been synced yet',
            (new CannotPurgeNotSyncedUserException(1000))->getMessage()
        );
    }
}
