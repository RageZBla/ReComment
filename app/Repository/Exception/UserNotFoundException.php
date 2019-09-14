<?php
declare(strict_types=1);

namespace App\Repository\Exception;


use App\AppException;

class UserNotFoundException extends \RuntimeException implements AppException
{

    /**
     * UserNotFoundException constructor.
     *
     * @param int $userId
     */
    public function __construct(int $userId)
    {
        $message = sprintf('user with ID: %d not found', $userId);
        parent::__construct($message);
    }
}
