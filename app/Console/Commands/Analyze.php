<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\BbAnalyzer;

class Analyze extends Command
{
    protected $signature = 'analyze
                            {--ida}
                            {--basic}
                            {--function}
                            {--flow}';
    protected $description = 'Analyze';

    private $anal;

    public function handle()
    {
        $this->anal = app(BbAnalyzer::class);

        if ($this->option('ida')) {
            $this->anal->parseFunc();
        }

        if ($this->option('basic')) {
            $this->anal->parseInfo();

            $this->anal->analyzeAllBlocks();
        }

        if ($this->option('flow')) {
            $this->anal->loadAll();
            $states = $this->anal->prepareStates();
            foreach ($this->anal->trace_log->parseLog() as $pkt_no => $chunk) {
                $states = $this->anal->buildIngress($chunk, $states);
                $this->anal->storeFlows($states);
            }
        }

        if ($this->option('function')) {
            $this->anal->assignSubroutines();
        }
    }
}

