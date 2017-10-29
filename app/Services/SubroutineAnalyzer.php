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

            printf("0x%x:\n", $block_id);

            foreach($mnemonics as $address => $mne) {
                $codes[] = (object)[
                    'address' => $address,
                    'code' => (string) $mne,
                    'writes' => $mne->getWrites(),
                    'reads' => $mne->getReads(),
                ];
            }

            dump($codes);

            $block->codes = $codes;
            $block->save();

            printf("------\n\n");
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
        // Form instruction
        $insn = app(BbAnalyzer::class)->disasmBlock($block);
        foreach($insn as &$ins) {
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
            case 'sub':
                $mne = new Decompiler\SubMne($block->id, $ins);
                break;
            case 'lea':
                $mne = new Decompiler\LeaMne($block->id, $ins);
                break;
            case 'or':
                $mne = new Decompiler\OrMne($block->id, $ins);
                break;
            case 'cmp':
                $mne = new Decompiler\CmpMne($block->id, $ins);
                break;
            case 'je':
            case 'jne':
            case 'jle':
            case 'ja':
            case 'jg':
                $mne = new Decompiler\JccMne($block->id, $ins);
                break;
            case 'jmp':
                $mne = new Decompiler\JmpMne($block->id, $ins);
                break;
            case 'sar':
                $mne = new Decompiler\SarMne($block->id, $ins);
                break;
            case 'ret':
                $mne = new Decompiler\RetMne($block->id, $ins);
                break;
            default:
                throw new Exception("Invalid ".$ins->mnemonic);
            }

            $mne->createOperands($state);
            $mne->detectReadsWrites();
            $state = $mne->process($state);
            $state->checkReadsWrites($mne, $this);

            if ($mne instanceof Decompiler\JccMne) {
                $mne->afterProcess($block, $this);
            }

            if (!isset($this->mnemonics[ $block->id ])) {
                $this->mnemonics[$block->id] = [];
            }

            $this->mnemonics[$block->id][$ins->address] = $mne;
        }

        return $state;
    }
}
