<?php
declare(strict_types=1);


namespace App\Service;


interface CachePurgerContract
{
    /**
     * Purge any objects that have not been accessed for N minutes
     *
     * @param int $numberMinutes
     */
    public function purge(int $numberMinutes): void;
}
