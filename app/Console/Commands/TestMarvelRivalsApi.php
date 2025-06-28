<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestMarvelRivalsApi extends Command
{
    protected $signature = 'test:marvel-rivals';
    protected $description = 'Test Marvel Rivals Professional Live Scoring System';

    public function handle()
    {
        $this->info('🚀 Testing Marvel Rivals Professional Live Scoring System...');
        
        // Include and run the test file
        include_once base_path('test_marvel_rivals_api.php');
        
        return 0;
    }
}