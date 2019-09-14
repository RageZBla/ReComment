<?php
declare(strict_types=1);

namespace Tests\Unit\App\Repository\Exception;

use App\AppException;
use PHPUnit\Framework\TestCase as Base;

abstract class TestCase extends Base
{

    protected function assertIsAppException(\Exception $exception): void
    {
        $this->assertInstanceOf(AppException::class, $exception, 'It should implement ' . AppException::class);
    }

    protected function assertRuntimeException(\Exception $exception): void
    {
        $this->assertInstanceOf(\RuntimeException::class, $exception, 'It should extend ' . \RuntimeException::class);
    }

    protected function assertLogicException(\Exception $exception): void
    {
        $this->assertInstanceOf(\LogicException::class, $exception, 'It should extend ' . \LogicException::class);
    }
}
