<?php

namespace App\Services;

use App\Subroutine;
use App\Code;
use App\Block;
use App\Symbol;
use App\Decompiler;

use Exception;

class SubroutineAnalyzer
{
    public $subroutine_id;
    public $block_id;

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

            if (in_array($block_id, $visits)) continue;

            array_push($visits, $block_id);

            if (array_key_exists($block_id, $blocks)) {

                printf("0x%x:\n", $block_id);

                $block = $blocks[$block_id];
                $state = $this->analyzeBlock($block, $state);

                if (! in_array($block->jump_mnemonic, ['ret'])) {
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

                printf("------\n\n");
            } else {
                $block = Block::find($block_id);
                if ($block) {
                    if ($block->subroutine_id != $subroutine_id) {
                        throw new Exception("Please analyze subs: 0x%x %s\n", $block->subroutine->id, $block->subroutine->name);
                    }
                } else {
                    $symbol = Symbol::find($block_id);
                    throw new Exception("Skip symbol: 0x%x %s\n", $symbol->id, $symbol->name);

                    // FIXME:
                    if ($symbol) {
                        $symbol->nextFlows->pluck('id')->each(function ($id) use (&$traces) {
                            array_push($traces, (object)[
                                'block_id' => $id,
                                'state' => clone $state
                            ]);
                        });
                    }
                }
            }
        }

        dump($return_states);
        if ($return_states[0]->esp > 0) {
            printf("STDCALL\n");
        } else {
            printf("CDECL\n");
        }

        return $return_states;
    }

    public function analyzeBlock($block, $state)
    {
        $this->block_id = $block->id;

        // Form instruction
        $insn = app(BbAnalyzer::class)->disasmBlock($block);
        foreach($insn as &$ins) {
            //printf("\t0x%x %s %s\n", $ins->address, $ins->mnemonic, $ins->op_str);
            $mne = null;

            switch ($ins->mnemonic) {
            case 'push':
                $mne = new Decompiler\PushMne($ins, $state, $this);
                break;
            case 'pop':
                $mne = new Decompiler\PopMne($ins, $state, $this);
                break;
            case 'mov':
                $mne = new Decompiler\MovMne($ins, $state, $this);
                break;
            case 'sub':
                $mne = new Decompiler\SubMne($ins, $state, $this);
                break;
            case 'lea':
                $mne = new Decompiler\LeaMne($ins, $state, $this);
                break;
            case 'or':
                $mne = new Decompiler\OrMne($ins, $state, $this);
                break;
            case 'cmp':
                $mne = new Decompiler\CmpMne($ins, $state, $this);
                break;
            case 'je':
            case 'jne':
            case 'jle':
            case 'ja':
            case 'jg':
                $mne = new Decompiler\JccMne($ins, $state, $this);
                break;
            case 'jmp':
                $mne = new Decompiler\JmpMne($ins, $state, $this);
                break;
            case 'sar':
                $mne = new Decompiler\SarMne($ins, $state, $this);
                break;
            case 'ret':
                $mne = new Decompiler\RetMne($ins, $state, $this);
                break;
            default:
                throw new Exception("Invalid ".$ins->mnemonic);
            }

            $state = $mne->process();
            printf("%s\n", $mne);
        }

        return $state;
    }

    public function emit($id, $codes, $params)
    {

    }
}
