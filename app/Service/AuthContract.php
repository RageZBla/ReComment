<?php
declare(strict_types=1);


namespace App\Service;


interface AuthContract
{
    /**
     * @param string $username
     *
     * @return string secret value to set to user cookie
     */
    public function login(string $username): string;
}
