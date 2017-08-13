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

        $dirty = false;
        $force = $this->option('force');

        $dirty |= $anal->doAssignJumpAndCallbacks($force);
        $dirty |= $anal->doAssignFunction($force);

        if ($this->option('replay')) {
            $dirty |= $anal->doAssignXref();
        }
        $dirty |= $anal->populateFunctionBlocks();

        if ($dirty) {
            fprintf(STDERR, "Saving trace log.\n");
            $anal->save($anal->trace_log);

            fprintf(STDERR, "Save analysis.\n");
            $anal->store();
        }

        //$anal->experiment();
    }
}

