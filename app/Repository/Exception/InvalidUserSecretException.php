<?php
declare(strict_types=1);

namespace App\Repository\Exception;


use App\AppException;
use Throwable;

class InvalidUserSecretException extends \InvalidArgumentException implements AppException
{

    public function __construct($message = 'Invalid secret provided', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
