<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\BbAnalyzer;

class Analyze extends Command
{
    protected $signature = 'analyze
                            {--replay : Replay traces to build Xrefs}
                            {--force : Force rerun basic analyze}';
    protected $description = 'Analyze';

    public function handle()
    {
        $anal = app(BbAnalyzer::class);
        $anal->doTheBest($this->option('force'));

        if ($this->option('replay')) {
            $dirty = $anal->doAssignXref();
            if ($dirty) {
                fprintf(STDERR, "Save analysis.\n");
                $anal->store();
            }
        }

        $anal->experiment();
    }
}

