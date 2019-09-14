<?php
declare(strict_types=1);

namespace App\Providers;

use App\Repository\CommentContract;
use App\Repository\CommentFetcherContract;
use App\Repository\CommentRepository;
use App\Repository\UserContract;
use App\Repository\UserFetcherContract;
use App\Repository\UserRepository;
use App\Service\AuthContract;
use App\Service\AuthService;
use App\Service\CachePurgerContract;
use App\Service\CachePurgerService;
use App\Service\CacheSyncContract;
use App\Service\MysqlCacheSyncService;
use App\Service\MysqlCommentFetcher;
use App\Service\MysqlUserFetcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(UserContract::class, UserRepository::class);
        $this->app->bind(CommentContract::class, CommentRepository::class);
        $this->app->bind(AuthContract::class, AuthService::class);
        $this->app->bind(CacheSyncContract::class, MysqlCacheSyncService::class);
        $this->app->bind(CachePurgerContract::class, CachePurgerService::class);
        $this->app->bind(CommentFetcherContract::class, MysqlCommentFetcher::class);
        $this->app->bind(UserFetcherContract::class, MysqlUserFetcher::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
    }
}
