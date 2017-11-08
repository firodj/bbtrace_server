<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BbAnalyzer;

class Analyze extends Command
{
    protected $signature = 'analyze
                            {--all}
                            {--basic}
                            {--flow}
                            {--over}
                            {--ida}
                            {--func}';
    protected $description = 'Analyze';

    private $anal;

    public function handle()
    {
        $this->anal = app(BbAnalyzer::class);


        if ($this->option('basic') || $this->option('all')) {
            $this->anal->parseInfo();
            $this->anal->analyzeAllBlocks();
        }

        if ($this->option('flow') || $this->option('all')) {
            $this->anal->parseFlowLog();
        }

        if ($this->option('over') || $this->option('all')) {
            $this->anal->fixOverlappedBlocks();
        }

        if ($this->option('ida') || $this->option('all')) {
            $this->anal->parseFunc();
        }

        if ($this->option('func') || $this->option('all')) {
            $this->anal->assignSubroutines();
        }
    }
}

