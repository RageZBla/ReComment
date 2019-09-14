<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Service\CacheSyncContract;
use Illuminate\Console\Command;

class Sync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize cache server with database';

    /**
     * @var CacheSyncContract
     */
    private $sync;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CacheSyncContract $sync)
    {
        parent::__construct();
        $this->sync = $sync;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $numberMinutes = (int)config('constants.sync_minutes');
        $this->output->writeln(sprintf('syncing objects that have been cached for %d minutes', $numberMinutes));
        $this->sync->sync($numberMinutes);
        $this->output->writeln('done.');
    }
}
