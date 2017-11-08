<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BbAnalyzer;

class Analyze extends Command
{
    protected $signature = 'analyze
                            {--basic}
                            {--flow}
                            {--ida}
                            {--func}';
    protected $description = 'Analyze';

    private $anal;

    public function handle()
    {
        $this->anal = app(BbAnalyzer::class);

        if ($this->option('basic')) {
            $this->anal->parseInfo();
            $this->anal->analyzeAllBlocks();
        }

        if ($this->option('flow')) {
            $this->anal->parseFlowLog();
            $this->anal->fixOverlappedBlocks();
        }

        if ($this->option('ida')) {
            $this->anal->parseFunc();
        }

        if ($this->option('func')) {
            $this->anal->assignSubroutines();
        }
    }
}

