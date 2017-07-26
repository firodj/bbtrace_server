<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\BbAnalyzer;

class Analyze extends Command
{
    protected $signature = 'analyze
                            {--replay : Replay traces to build Xrefs}
                            {--pass : Pass over basic analy}';
    protected $description = 'Analyze';

    public function handle()
    {
        $fname = base_path('../logs/psxfin.bb_analyzer.dump');

        if (file_exists($fname)) {
            $anal = BbAnalyzer::restore($fname);
        } else {
            $anal = new BbAnalyzer($fname);
            $anal->open(base_path('../logs/psxfin.trace_log.dump'));
            $anal->open(base_path('../logs/psxfin.pe_parser.dump'));
        }

        if (!$this->option('pass')) {
            $anal->doTheBest();
        }

        if ($this->option('replay')) {
            $dirty = $this->doAssignXref();
            if ($dirty) {
                self::store($this);
            }
        }
    }
}

