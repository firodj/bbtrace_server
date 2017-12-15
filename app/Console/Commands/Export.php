<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BbAnalyzer;
use App\Block;

class Export extends Command
{
    protected $signature = 'export
                            {--flow}
                            {--color}';
    protected $description = 'Export IDC';

    private $anal;

    public function handle()
    {
        if ($this->option('flow')) {
            $this->handleFlow();
        }
        if ($this->option('color')) {
            $this->handleColor();
        }
    }

    public function handleFlow()
    {
        echo <<<EOS
#include <idc.idc>

static list_cref(ea)
{
    auto x;
    msg("\\n*** Code references from " + atoa(ea) + "\\n");
    for ( x=get_first_cref_from(ea); x != BADADDR; x=get_next_cref_from(ea,x) ) {
        msg(atoa(ea) + " refers to " + atoa(x) + "\\n");
    }
}

static main()
{

EOS;


        Block::where('jump_mnemonic', 'call')->whereNull('jump_dest')->get()->each(function ($block)
        {
            $block->nextFlows()->where('block_type', 'blocks')->get()->each(function($flow) use ($block)
            {
                if ($flow->block->addr != $block->end) {
                    $ea = dechex($block->jump_addr);
                    $dest = dechex($flow->block->addr);
                    echo <<<EOS
    add_cref(0x$ea, 0x$dest, fl_CN);
    list_cref(0x$ea);

EOS;

                    // $flow->block->addr;
                }
            });
        });

        Block::where('jump_mnemonic', 'like', 'j%')->whereNull('jump_dest')->get()->each(function ($block)
        {
            $block->nextFlows()->where('block_type', 'blocks')->get()->each(function($flow) use ($block)
            {
                if ($flow->block->addr != $block->end) {
                    $ea = dechex($block->jump_addr);
                    $dest = dechex($flow->block->addr);
                    echo <<<EOS
    add_cref(0x$ea, 0x$dest, fl_JN);
    list_cref(0x$ea);

EOS;

                    // $flow->block->addr;
                }
            });
        });

        echo <<<EOS
}
EOS;
    }

    public function handleColor()
    {
            echo <<<EOS
#include <idc.idc>

static color_instr(ea, maxea, col)
{
    auto x;
    msg("\\n*** Coloring instructions from " + atoa(ea) + "\\n");
    for ( x=ea; x != BADADDR; x=next_head(x,maxea) ) {
        set_color(x, CIC_ITEM, col);
    }
}

static main()
{

EOS;

            Block::get()->each(function($block) {
                $ea = dechex($block->addr);
                $maxea = dechex($block->end);

                echo <<<EOS
    color_instr(0x$ea, 0x$maxea, 0xccffff);

EOS;
            });

        echo <<<EOS
}
EOS;

    }
}
