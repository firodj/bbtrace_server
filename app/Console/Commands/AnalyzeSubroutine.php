<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SubroutineAnalyzer;

class AnalyzeSubroutine extends Command
{
    protected $signature = 'analyze:subroutine
                            {id : Subroutine Id}
                            {--exgen : expression generate}
                            {--binary=BINARY : output binary}';

    protected $description = 'Analyze Subroutine';

    private $anal;

    public function handle()
    {
        $id = $this->argument('id');
        if (strpos($id, '0x') === 0) {
            $id = hexdec($id);
        }

        $this->anal = new SubroutineAnalyzer();

        if ($this->option('exgen')) {
            $this->anal->exgen($id);
            return;
        }

        if ($binaryFile = $this->option('binary')) {
            $data = $this->anal->binary($id, true);
            if (file_exists($binaryFile)) {
                $this->error("File exists $binaryFile");
            } else {
                file_put_contents($binaryFile, $data);
            }
            return;
        }

        $this->anal->analyze($id);
    }
}
