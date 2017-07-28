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
        $anal = app(BbAnalyzer::class);

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

