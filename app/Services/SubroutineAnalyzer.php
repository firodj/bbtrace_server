<?php

namespace App\Services;

use App\Subroutine;
use App\Code;
use App\Block;
use App\Symbol;
use App\Decompiler;
use PhpAnsiColor\Color;

use Exception;

class SubroutineAnalyzer
{
    public $subroutine_id;
    public $reg_revisions = [];
    public $mnemonics = [];

    public function analyze(int $subroutine_id)
    {
        $subroutine = Subroutine::findOrFail($subroutine_id);
        $this->subroutine_id;

        printf("Analyze subs: 0x%x %s\n", $subroutine->id, $subroutine->name);

        $blocks = [];
        $subroutine->blocks->each(function ($item) use(&$blocks) {
            $blocks[$item->id] = $item;
        });

        $traces = [
            (object)[
                'block_id' => $subroutine_id,
                'state' => new Decompiler\State()
            ]
        ];
        $visits = [];

        $return_states = [];

        while ($trace_item = array_pop($traces)) {
            $block_id = $trace_item->block_id;
            $state = clone $trace_item->state;
            $state->block_id = $block_id;

            if (in_array($block_id, $visits)) continue;

            array_push($visits, $block_id);

            if (array_key_exists($block_id, $blocks)) {
                $block = $blocks[$block_id];
                $state = $this->analyzeBlock($block, $state);

                if ($block->jump_mnemonic == 'call') {
                    array_push($traces, (object)[
                        'block_id' => $block->end,
                        'state' => clone $state
                    ]);
                }

                if ($block->jump_mnemonic != 'ret') {
                    $block->nextFlows->pluck('id')->each(function ($id) use (&$traces, $state) {
                        array_push($traces, (object)[
                            'block_id' => $id,
                            'state' => clone $state
                        ]);
                    });
                }

                if ($block->jump_mnemonic == 'ret') {
                    $return_states[] = clone $state;
                }
            } else {
                $block = Block::find($block_id);
                if ($block) {
                    if ($block->subroutine_id != $subroutine_id) {
                        if (is_null($block->subroutine->esp)) {
                            printf(Color::set(
                                sprintf("Please analyze subs: 0x%x %s\n", $block->subroutine->id, $block->subroutine->name)
                            ,
                            'yellow'));
                        }
                    }
                } else {
                    $symbol = Symbol::find($block_id);
                    printf(Color::set(
                        sprintf("Skip symbol: 0x%x %s\n", $symbol->id, $symbol->getDisplayName())
                    ,
                    'yellow'));

                    // if ($symbol) {
                    //     $symbol->nextFlows->pluck('id')->each(function ($id) use (&$traces) {
                    //         array_push($traces, (object)[
                    //             'block_id' => $id,
                    //             'state' => clone $state
                    //         ]);
                    //     });
                    // }
                }
            }
        }

        $returns = array_map(function ($return_state) use(&$subroutine) {
            if (is_null($subroutine->esp)) {
                $subroutine->esp = $return_state->esp;
            } else if ($subroutine->esp != $return_state->esp) {
                throw new Exception('ESP different each returns');
            }
            if (is_null($subroutine->arg) || ($subroutine->arg < $return_state->arg)) {
                $subroutine->arg = $return_state->arg;
            }
            return $return_state->toArray();
        }, $return_states);

        $subroutine->returns = $returns;
        $subroutine->save();

        foreach($this->mnemonics as $block_id => $mnemonics) {
            $codes = [];
            $block = Block::findOrFail($block_id);
            foreach($mnemonics as $address => $mne) {
                $codes[] = (object)[
                    'address' => $address,
                    'code' => (string) $mne,
                    'writes' => $mne->getWrites(),
                    'reads' => $mne->getReads(),
                ];
            }

            $block->codes = $codes;
            $block->save();
        }

        dump($returns);

        foreach($this->reg_revisions as $reg => $revisions) {
            foreach ($revisions as $rev => $revision) {
                // dump($revision);
                printf("%s@%d used:%d\t", $reg, $rev, count($revision->read_by));
            }
        }
        // printf(($subroutine->esp > 0) ? "STDCALL" : "CDECL");

        return $return_states;
    }

    public function analyzeBlock($block, $state)
    {
        echo Color::set(sprintf("\n0x%x:\n", $block->id), 'bold+underline');

        // Form instruction
        $insn = app(BbAnalyzer::class)->disasmBlock($block);
        foreach($insn as &$ins) {
            echo "\t";
            echo Color::set(sprintf("0x%x: ", $ins->address), 'yellow');
            echo Color::set(sprintf("%s\t", $ins->mnemonic), 'blue');
            echo Color::set(sprintf("%s\n", $ins->op_str), 'magenta');

            if ($ins->mnemonic == 'mov') {
                //dump($ins);
            }

            $mne = null;
            switch ($ins->mnemonic) {
            case 'push':
                $mne = new Decompiler\PushMne($block->id, $ins);
                break;
            case 'pop':
                $mne = new Decompiler\PopMne($block->id, $ins);
                break;
            case 'mov':
                $mne = new Decompiler\MovMne($block->id, $ins);
                break;
            case 'movzx':
                $mne = new Decompiler\MovzxMne($block->id, $ins);
                break;
            case 'movsx':
                $mne = new Decompiler\MovsxMne($block->id, $ins);
                break;
            case 'sub':
                $mne = new Decompiler\SubMne($block->id, $ins);
                break;
            case 'sbb':
                $mne = new Decompiler\SbbMne($block->id, $ins);
                break;
            case 'add':
                $mne = new Decompiler\AddMne($block->id, $ins);
                break;
            case 'lea':
                $mne = new Decompiler\LeaMne($block->id, $ins);
                break;
            case 'or':
                $mne = new Decompiler\OrMne($block->id, $ins);
                break;
            case 'xor':
                $mne = new Decompiler\XorMne($block->id, $ins);
                break;
            case 'cmp':
                $mne = new Decompiler\CmpMne($block->id, $ins);
                break;
            case 'test':
                $mne = new Decompiler\TestMne($block->id, $ins);
                break;
            case 'je':
            case 'jne':
            case 'jle':
            case 'ja':
            case 'jbe':
            case 'jb':
            case 'jg':
                $mne = new Decompiler\JccMne($block->id, $ins);
                break;
            case 'jmp':
                $mne = new Decompiler\JmpMne($block->id, $ins);
                break;
            case 'call':
                $mne = new Decompiler\CallMne($block->id, $ins);
                break;
            case 'sar':
                $mne = new Decompiler\SarMne($block->id, $ins);
                break;
            case 'shl':
                $mne = new Decompiler\ShlMne($block->id, $ins);
                break;
            case 'ret':
                $mne = new Decompiler\RetMne($block->id, $ins);
                break;
            case 'nop':
                $mne = new Decompiler\NopMne($block->id, $ins);
                break;
            default:
                throw new Exception("Invalid ".$ins->mnemonic);
            }

            $mne->createOperands($state);
            $mne->detectReadsWrites();
            $state = $mne->process($state);
            $state->checkReadsWrites($mne, $this);
            $state = $mne->afterProcess($block, $this, $state);

            if (!isset($this->mnemonics[ $block->id ])) {
                $this->mnemonics[$block->id] = [];
            }

            printf("%s", (string) $mne);
            printf("  // w(%s) r(%s)\n",
                Color::set(implode(" ", $mne->getWrites()), 'bold+red'),
                Color::set(implode(" ", $mne->getReads()), 'bold+green')
            );


            $this->mnemonics[$block->id][$ins->address] = $mne;
        }

        return $state;
    }
}
