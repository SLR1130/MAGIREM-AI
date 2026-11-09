<?php

namespace App\Console\Commands;

use App\Services\DailyAggregatorService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateDailyEmotions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emotion:aggregate-daily {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate daily emotional scores for all students';

    /**
     * Execute the console command.
     */
    public function handle(DailyAggregatorService $aggregatorService)
    {
        $date = $this->argument('date') 
            ? Carbon::parse($this->argument('date'))
            : Carbon::today();
        
        $this->info("Starting daily aggregation for {$date->toDateString()}...");
        
        $result = $aggregatorService->aggregateDaily($date);
        
        $this->info("Daily aggregation completed!");
        $this->info("Students processed: {$result['processed']}");
        $this->info("Summaries created: {$result['summaries_created']}");
        
        return Command::SUCCESS;
    }
}
