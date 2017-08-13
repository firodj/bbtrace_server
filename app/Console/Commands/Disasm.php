<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\BbAnalyzer;

class Disasm extends Command
{
    protected $signature = 'disasm
                            {block_id : Block entry in hex (0x) or dec}
                            {--detail : Details}
                            ';
    protected $description = 'Disasm';

    public function handle()
    {
        $anal = app(BbAnalyzer::class);

        $block_id = $this->argument('block_id');
        $detail = $this->option('detail');

        if (strpos($block_id, '0x') === 0) {
            $block_id = hexdec($block_id);
        }
        $anal->print_disasm($block_id, $detail);
    }
}

