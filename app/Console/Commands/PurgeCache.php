<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Service\CachePurgerContract;
use Illuminate\Console\Command;

class PurgeCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge out of the cache any objects that have been stalled for ten minutes';
    /**
     * @var CachePurgerContract
     */
    private $cachePurger;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CachePurgerContract $cachePurger)
    {
        parent::__construct();
        $this->cachePurger = $cachePurger;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // XXX: uncomment to test purge
//        \Carbon\Carbon::setTestNow(Carbon::tomorrow());

        $minutes = (int)config('constants.purge_minutes');
        $this->output->writeln(sprintf('purgin stalled object for %d minute(s)', $minutes));
        $this->cachePurger->purge($minutes);
        $this->output->writeln('done.');
    }
}
