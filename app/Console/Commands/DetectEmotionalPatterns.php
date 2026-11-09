<?php

namespace App\Console\Commands;

use App\Services\PatternDetectorService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DetectEmotionalPatterns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emotion:detect-patterns {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect emotional patterns and generate alerts for counselors';

    /**
     * Execute the console command.
     */
    public function handle(PatternDetectorService $detectorService)
    {
        $date = $this->argument('date') 
            ? Carbon::parse($this->argument('date'))
            : Carbon::today();
        
        $this->info("Starting pattern detection for {$date->toDateString()}...");
        
        $result = $detectorService->analyzePatterns($date);
        
        $this->info("Pattern detection completed!");
        $this->info("Students analyzed: {$result['students_analyzed']}");
        $this->info("Alerts generated: {$result['alerts_generated']}");
        
        return Command::SUCCESS;
    }
}
