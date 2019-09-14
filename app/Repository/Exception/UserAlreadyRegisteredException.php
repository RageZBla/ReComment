<?php
declare(strict_types=1);

namespace App\Repository\Exception;


use App\AppException;

class UserAlreadyRegisteredException extends \RuntimeException implements AppException
{

    /**
     * UserAlreadyRegisteredException constructor.
     *
     * @param string $username
     */
    public function __construct(string $username)
    {
        $message = sprintf('"%s" is already registered', $username);
        parent::__construct($message);
    }
}
