<?php
declare(strict_types=1);

namespace App\Repository\Exception;


use App\AppException;

class CannotPurgeNotSyncedCommentException extends \LogicException implements AppException
{

    /**
     * CannotPurgeNotSyncedCommentException constructor.
     *
     * @param int $commentId
     */
    public function __construct(int $commentId)
    {

        $message = sprintf('comment with ID: %d can not be purged because it has not been synced yet', $commentId);
        parent::__construct($message);
    }
}
