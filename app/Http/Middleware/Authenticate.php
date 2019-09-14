<?php
declare(strict_types=1);

namespace App\Http\Middleware;


use App\Repository\Exception\InvalidUserSecretException;
use App\Repository\UserContract;
use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;

class Authenticate
{
    /**
     * @var UserContract
     */
    private $userRepository;

    /**
     * @var ViewFactory
     */
    private $view;

    public function __construct(UserContract $userRepository, ViewFactory $view)
    {
        $this->userRepository = $userRepository;
        $this->view = $view;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function handle($request, Closure $next)
    {
        $userId = null;
        $username = null;
        if ($request->hasCookie('auth')) {
            $secret = $request->cookie('auth');
            try {
                $userId = $this->userRepository->getUserIdBySecret($secret);
                $username = $this->userRepository->getUsername($userId);
            } catch (InvalidUserSecretException $e) {
                // XXX: logs
            }
        }
        $this->view->share('username', $username);
        $request['_user_id'] = $userId;
        $request['_username'] = $username;

        return $next($request);
    }
}
