<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SubroutineAnalyzer;

class AnalyzeSubroutine extends Command
{
    protected $signature = 'analyze:subroutine
                            {id : Subroutine Id}';

    protected $description = 'Analyze Subroutine';

    private $anal;

    public function handle()
    {
        $id = $this->argument('id');
        if (strpos($id, '0x') === 0) {
            $id = hexdec($id);
        }

        $this->anal = new SubroutineAnalyzer();

        $this->anal->analyze($id);

    }
}


