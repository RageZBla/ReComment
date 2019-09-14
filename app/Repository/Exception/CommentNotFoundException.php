<?php
declare(strict_types=1);

namespace App\Repository\Exception;


use App\AppException;

class CommentNotFoundException extends \RuntimeException implements AppException
{

    public function __construct(int $commentId)
    {
        $message = sprintf('comment with ID: %d not found', $commentId);
        parent::__construct($message);
    }
}
