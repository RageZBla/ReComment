<?php
declare(strict_types=1);

namespace App\Service;


use App\Repository\UserContract;

class AuthService implements AuthContract
{

    /**
     * @var UserContract
     */
    private $userRepository;

    public function __construct(UserContract $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @param string $username
     *
     * @return string secret value to set to user cookie
     */
    public function login(string $username): string
    {
        if (empty($username)) {
            throw new \InvalidArgumentException();
        }

        if ($this->isUserRegistered($username)) {
            $userId = $this->getUserId($username);
            return $this->getUserAuthSecret($userId);
        }

        $authSecret = $this->generateAuthSecret();
        $this->userRepository->addUser($username, $authSecret);

        return $authSecret;
    }

    private function getUserId(string $username): int
    {
        return $this->userRepository->getUserID($username);
    }

    private function isUserRegistered(string $username): bool
    {
        return $this->userRepository->isRegistered($username);
    }

    private function getUserAuthSecret(int $userId): string
    {
        return $this->userRepository->getUserAuthSecret($userId);
    }

    private function generateAuthSecret(): string
    {
        $fd = fopen('/dev/urandom', 'r');
        $data = fread($fd, 16);
        fclose($fd);
        return md5($data);
    }
}
