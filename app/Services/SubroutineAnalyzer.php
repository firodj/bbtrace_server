<?php

namespace App\Services;

use App\Subroutine;
use App\Block;
use App\Symbol;

class SubroutineAnalyzer
{
    public function analyze(int $subroutine_id)
    {
        $stacks = [];
        $subs = [$subroutine_id];

        while($sub_id = array_pop($subs)) {
            $subroutine = Subroutine::find($sub_id);
            $stacks[$sub_id] = $subroutine;

            printf("Analyze subs: 0x%x %s\n", $subroutine->id, $subroutine->name);

            $blocks = [];
            $subroutine->blocks->each(function ($item) use(&$blocks) {
                $blocks[$item->id] = $item;
            });

            $traces = [$sub_id];
            $visits = [];

            while ($block_id = array_pop($traces)) {
                if (in_array($block_id, $visits)) continue;

                if (array_key_exists($block_id, $blocks)) {
                    $block = $blocks[$block_id];

                    if (! in_array($block->jump_mnemonic, ['ret'])) {
                        $block->nextFlows->pluck('id')->each(function ($id) use (&$traces) {
                            array_push($traces, $id);
                        });
                    }

                    printf("0x%x\t%s\n", $block->id, $block->jump_mnemonic);
                }

                $block = Block::find($block_id);
                if ($block) {
                    if ($block->subroutine_id != $sub_id) {
                        printf("Please analyze subs: 0x%x %s\n", $block->subroutine->id, $block->subroutine->name);
                    }
                } else {
                    $symbol = Symbol::find($block_id);
                    printf("Skip symbol: 0x%x %s\n", $symbol->id, $symbol->name);
                    if ($symbol) {
                        $symbol->nextFlows->pluck('id')->each(function ($id) use (&$traces) {
                            array_push($traces, $id);
                        });
                    }
                }

                array_push($visits, $block_id);
            }

        }
    }

}
