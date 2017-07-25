<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\BbAnalyzer;

class Analyze extends Command
{
    protected $signature = 'analyze';
    protected $description = 'Analyze';

    public function handle()
    {
        $anal = new BbAnalyzer();
        $anal->open(base_path('../logs/psxfin.trace_log.dump'));
        $anal->open(base_path('../logs/psxfin.pe_parser.dump'));

        $anal->experiment2();
    }
}

