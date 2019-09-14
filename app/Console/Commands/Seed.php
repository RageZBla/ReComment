<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Repository\CommentContract;
use App\Repository\UserContract;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\DB;

class Seed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the cache server with random data';

    /**
     * @var UserContract
     */
    private $userRepository;

    /**
     *
     * @var CommentContract
     */
    private $commentRepository;

    /**
     * @var RedisManager
     */
    private $redisManager;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        RedisManager $redisManager,
        UserContract $userRepository,
        CommentContract $commentRepository
    ) {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->commentRepository = $commentRepository;
        $this->redisManager = $redisManager;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->redisManager->flushdb();
        $tables = ['comments', 'users', 'comment_like'];
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        $faker = Factory::create();
        $now = CarbonImmutable::now();
        $userIds = [];
        $userIdToUsername = [];

        // Users
        $numberUsers = config('constants.seed.number_users');
        $this->output->writeln(sprintf('seeding %d users', $numberUsers));
        foreach (range(1, $numberUsers) as $n) {
            Carbon::setTestNow($now->subDays($n));
            $username = $faker->firstName;
            $userId = $this->userRepository->addUser($username, 'secret' . $n);
            $userIds[] = $userId;
            $userIdToUsername[$userId] = $username;
        }

        // Comments
        $numberComments = config('constants.seed.number_comments');
        $this->output->writeln(sprintf('seeding %d comments', $numberComments));
        $numberMinutes = (int)config('constants.sync_minutes');
        foreach (range(1, $numberComments) as $n) {
            Carbon::setTestNow($now->subMinutes($n + $numberMinutes)); // To make sure it would get sync
            $userId = $userIds[array_rand($userIds)];
            $username = $userIdToUsername[$userId];
            $this->commentRepository->post(implode(PHP_EOL, $faker->paragraphs(3)), $userId, $username);
        }

        // XXX: Not really needed but...
        Carbon::setTestNow(null);

        $this->output->writeln('seeded.');
    }
}
