<?php

namespace App\Services;

use App\Subroutine;
use App\Code;
use App\Block;
use App\Symbol;
use App\DefUse;
use App\Decompiler;
use App\Expression;
use PhpAnsiColor\Color;
use App\Services\Decompiler\State;
use App\Services\Decompiler\RegVal;
use App\Services\Decompiler\BlockWrap;

use Exception;

class SubroutineAnalyzer
{
    /**
     * Subroutine
     * @var Subroutine $subroutine
     */
    public $subroutine;

    /**
     * Return states
     * @var array<int, State> $returns
     */
    public $returns;

    public $verbose;

    public $reg_revisions = [];
    public $mnemonics = [];

    public function __construct($subroutine_id)
    {
        $this->subroutine = Subroutine::with('blocks')->with('module')->findOrFail($subroutine_id);
        $this->returns = [];
        $this->verbose = true;
    }

    public function binary(bool $whole)
    {
        printf("Binary subs #%d: 0x%x - 0x%x (%d) %s\n", $this->subroutine->id,
            $this->subroutine->addr,
            $this->subroutine->end,
            $this->subroutine->getSize(),
            $this->subroutine->name);

        if ($whole) {
            $result = app(BbAnalyzer::class)->pe_parser->getBinaryByRva(
                $this->subroutine->getRva(),
                $this->subroutine->getSize()
            );
            return $result;
        }

        $result = '';

        $prev = null;
        foreach ($this->subroutine->blocks()->orderBy('addr')->get() as $block) {
            $data = app(BbAnalyzer::class)->pe_parser->getBinaryByRva($block->getRva(), $block->getSize());
            if (!is_null($prev)) {
                 // NOP missing block
                if ($prev->end != $block->addr) {
                    printf("Missing: 0x%x - 0x%x\n", $prev->end, $block->addr);
                    $size = $block->addr - $prev->end;
                    $result .= str_repeat("\x90", $size);
                }
            }
            printf("Block #%d: 0x%x - 0x%x\n", $block->id, $block->addr, $block->end);
            $result .= $data;
            $prev = $block;
        };

        return $result;
    }

    public function eachBlock()
    {
        $block_wraps = [];
        foreach ($this->subroutine->blocks as $block) {
            $block_wraps[$block->addr] = new BlockWrap($block);
        }

        $traces = [
            (object)[
                'block_addr' => $this->subroutine->addr,
                'state' => State::createState()
            ]
        ];
        $visits = [];

        $returns = [];

        while ($trace_item = array_pop($traces)) {
            if ($block_wrap = $block_wraps[$trace_item->block_addr] ?? null) {
                $in_state = clone $trace_item->state;

                $is_new_layer = null;
                $is_skip = false;

                foreach ($block_wrap->in_states as $other_layer_key => $other_state) {
                    if ($other_layer_key == $in_state->layerKey()) $is_skip = true;

                    if ($other_state->block_id == $in_state->block_id) {
                        $is_new_layer = false;
                    } else if ($is_new_layer !== false) {
                        $is_new_layer = true;
                    }
                }

                if ($is_new_layer === true) {

                    if (! in_array($in_state->block_id, $in_state->layer)) {

                        $new_state = clone $in_state;
                        $new_state->layer[] = $new_state->block_id;
                        $traces[] = (object)[
                            'block_addr' => $block_wrap->block->addr,
                            'state' => $new_state
                        ];

                        fprintf(STDERR, "%s",
                            Color::set(sprintf("New layer: #%d [%s]\n", $block_wrap->block->addr, $new_state->layerKey()), 'yellow')
                        );
                    }
                }

                if ($is_skip) continue;

                $block_wrap->in_states[$in_state->layerKey()] = $in_state;

                $state = clone $in_state;
                $state->block_id = $block_wrap->block->id;

                if ($block_wrap->block->instructions()->count() == 0) {
                    app(BbAnalyzer::class)->disasmBlock($block_wrap->block);
                }

                yield $block_wrap->block => $state;

                if ($block_wrap->block->jump_mnemonic == 'call') {
                    $traces[] = (object)[
                        'block_addr' => $block_wrap->block->end,
                        'state' => clone $state
                    ];
                }

                if ($block_wrap->block->jump_mnemonic != 'ret') {
                    foreach ($block_wrap->block->nextFlows as $flow) {
                        $traces[] = (object)[
                            'block_addr' => $flow->block->addr,
                            'state' => clone $state
                        ];
                    };
                }

                if ($block_wrap->block->jump_mnemonic == 'ret') {
                    $returns[$block_wrap->block->addr] = clone $state;
                }
            } else {
                $block = Block::where('addr', $trace_item->block_addr)->first();
                if ($block && $block->subroutine_id != $this->subroutine->id) {
                    $returns[$block->addr] = clone $state;
                }
            }
        }

        $this->returns = $returns;
    }

    public function blockDefUse(Block $block, State $state)
    {
        if ($this->verbose) {
            echo Color::set(sprintf("\n%d #%d:", $block->addr, $block->id), 'bold+underline');
            echo Color::set(sprintf(" %X", $block->addr), 'underline');
            echo Color::set(sprintf("\t%s", $state->layerKey()), 'cyan');
            echo "\n";
        }

        // Form instruction
        foreach($block->instructions as $inst) {
            if ($this->verbose) {
                echo "\t";
                echo Color::set(sprintf("%d #%d: ", $inst->addr, $inst->id), 'yellow');
                echo Color::set(sprintf("%s", $inst->toString()), 'blue');
            }

            $anal = new DefUseAnalyzer($inst, $state);
            $anal->analyze();

            if ($this->verbose) {
                echo Color::set(sprintf("\t%s", implode(',', $anal->uses)), 'green');
                echo Color::set(sprintf("\t%s", implode(',', $anal->defs)), 'red');

                echo "\n";
            }
        }
    }

    public function analyzeDefUse()
    {
        foreach ($this->eachBlock() as $block => $state) {
            $this->blockDefUse($block, $state);
        }

        $state = reset($this->returns);
        $reg_defs = $state->reg_defs;

        foreach ($reg_defs->reg_defs as $reg_def) {
            foreach ($reg_def->defs as $reg_defuse) {
                foreach ($reg_defuse->uses as $inst_id) {
                    $attrs = [
                        'instruction_id' => $inst_id,
                        'reg' => $reg_defuse->reg,
                        'defined_instruction_id' => $reg_defuse->inst_id
                    ];

                    $defuse = DefUse::where($attrs)->first();
                    if (! $defuse) {
                        $defuse = new DefUse;
                        $defuse->fill($attrs);
                        $defuse->save();
                    }
                }
            }

            $reg_defuse = $reg_def->latestDef($state);
            if (empty($reg_defuse->uses) && $reg_defuse->rev != 0) {
                $attrs = [
                    'instruction_id' => null,
                    'reg' => $reg_defuse->reg,
                    'defined_instruction_id' => $reg_defuse->inst_id
                ];

                $defuse = DefUse::where($attrs)->first();
                if (! $defuse) {
                    $defuse = new DefUse;
                    $defuse->fill($attrs);
                    $defuse->save();
                }
            }
        }
    }

    public function analyzeValue()
    {
        foreach ($this->eachBlock() as $block => $state) {
            $this->blockValue($block, $state);
        }

        $state = reset($this->returns);
        $reg_defs = $state->reg_defs;

        if ($this->verbose) {
            echo Color::set(sprintf("ESP offset: %d\n", $state->reg_vals['esp']->disp), 'blue');
        }
    }

    public function blockValue(Block $block, State $state)
    {
        if ($this->verbose) {
            echo Color::set(sprintf("\n%d #%d:", $block->addr, $block->id), 'bold+underline');
            echo Color::set(sprintf(" %X", $block->addr), 'underline');
            echo Color::set(sprintf("\t%s", $state->layerKey()), 'cyan');
            echo "\n";
        }

        // Form instruction
        foreach($block->instructions as $inst) {
            if ($this->verbose) {
                echo "\t";
                echo Color::set(sprintf("%d #%d: ", $inst->addr, $inst->id), 'yellow');
                echo Color::set(sprintf("%s", $inst->toString()), 'blue');
            }

            $anal = new ValueAnalyzer($inst, $state);
            $anal->analyze();

            if ($this->verbose) {
                foreach ($anal->uses as $reg => $reg_val) {
                    switch ($reg_val->type) {
                    case RegVal::CONST_TYPE:
                        echo Color::set(sprintf("\t%s: %s", $reg, $reg_val->disp), 'green');
                        break;
                    case RegVal::OFFSET_TYPE:
                        echo Color::set(sprintf("\t%s: [%s @ %s] + %s", $reg, $reg_val->reg, $reg_val->def_inst_id, $reg_val->disp), 'green');
                        break;
                    }
                }

                foreach ($anal->changes as $reg => $reg_val) {
                    switch ($reg_val->type) {
                    case RegVal::CONST_TYPE:
                        echo Color::set(sprintf("\t%s: %s", $reg, $reg_val->disp), 'red');
                        break;
                    case RegVal::OFFSET_TYPE:
                        echo Color::set(sprintf("\t%s: [%s @ %s] + %s", $reg, $reg_val->reg, $reg_val->def_inst_id, $reg_val->disp), 'red');
                        break;
                    }
                }

                echo "\n";
            }
        }
    }

    /**
     * TODO: refactor
     */
    public function graph()
    {
        $subroutine = $this->subroutine;

        $result = $subroutine->toArray();
        $links = [];
        $aliens = [];

        $result['blocks'] = $subroutine->blocks->map(function ($block) use (&$aliens, &$links) {
            // mark this block is not alien

            $a = make_key($block);
            $aliens[ $a ] = false;

            // Form flow
            foreach($block->nextFlows as $flow) {
                if ($block->jump_mnemonic == 'ret') {
                    continue;
                }

                $b = make_key($flow->block);
                if ($block->jump_mnemonic == 'call') {
                    $is_calling = true;
                    if ($flow->block->addr == $block->end) {
                        $is_calling = false;
                    } else {
                        $b .= '_'.count($aliens);
                    }
                }

                $key = sprintf("%s-%s", $a, $b);

                $condition = null;
                if (is_string($block->jump_mnemonic) && $block->jump_mnemonic[0] == 'j' && $block->jump_mnemonic != 'jmp') {
                    $condition = $block->end != $flow->block->addr;
                }

                $links[$key] = [
                    'source_id' => $a,
                    'target_id' => $b,
                    'key' => $key,
                    'condition' => $condition,
                ];

                if (! array_key_exists( $b, $aliens)) {
                    $aliens[ $b ] = true;
                }

                if ($block->jump_mnemonic == 'call') {
                    $succ_block = Block::where('addr', $block->end)->first();
                    if ($succ_block && $is_calling) {
                        $c = make_key($succ_block);

                        $key = sprintf("%s-%s", $b, $c);
                        $links[$key] = [
                            'source_id' => $b,
                            'target_id' => $c,
                            'key' => $key,
                            'condition' => null,
                        ];
                        if (! array_key_exists($c, $aliens)) {
                            $aliens[ $c ] = true;
                        }
                    }
                }
            }

            // If no codes disasm
            if (! $block->codes ) {
                $codes = [];
                if ($block->instructions->count() == 0) {
                    app(BbAnalyzer::class)->disasmBlock($block);
                }
                $codes = $block->instructions()->get()->map(function ($inst)
                {
                    return ['code' => $inst->toString()];
                });
                $block->codes = $codes;
            }

            $result = $block->toArray();

            $result['type'] = 'block';
            $result['id'] = $a;

            return $result;
        });

        $result['links'] = array_values($links);

        foreach($aliens as $id => $value) {
            if (! $value) continue;

            $alien = [
                'id' => $id,
                'type' => 'unknown'
            ];

            $keyz = explode('_', $id);

            if ($keyz[0] == 'subroutines') {
                $subroutine = Subroutine::find($keyz[1]);
                if ($subroutine) {
                    $alien = [
                        'id' => $id,
                        'addr' => $subroutine->addr,
                        'type' => 'subroutine',
                        'name' => $subroutine->name,
                    ];
                }
            }
            else if ($keyz[0] == 'symbols') {
                $symbol = Symbol::with('module')->find($keyz[1]);
                if ($symbol) {
                    $alien = [
                        'id' => $id,
                        'addr' => $symbol->addr,
                        'type' => 'symbol',
                        'name' => $symbol->getDisplayName()
                    ];
                }
            }
            else if ($keyz[0] == 'blocks') {
                $block = Block::with('subroutine')->find($keyz[1]);
                if ($block) {
                    $alien = [
                        'id' => $id,
                        'addr' => $block->addr,
                        'type' => 'other',
                        'name' => $block->subroutine->name
                    ];
                }
            }

            $result['blocks'][] = $alien;
        }

        return $result;
    }

    /**
     * Generate operand's expression for all instructions
     * @deprecated
     */
    public function exgen(int $subroutine_id)
    {
        $subroutine = Subroutine::findOrFail($subroutine_id);
        $this->subroutine_id = $subroutine_id;

        $subroutine->blocks->each(function ($block) {
            if ($block->instructions()->count() == 0) {
                app(BbAnalyzer::class)->disasmBlock($block);
            }

            foreach($block->instructions as $inst) {
                foreach ($inst->operands as $opnd) {
                    Expression::createExpressionFromOperand($opnd);
                }
                echo $inst->toString() . PHP_EOL;
            }
        });
    }


    /**
     * @deprecated
     */
    public function analyze(int $subroutine_id)
    {
        $subroutine = Subroutine::findOrFail($subroutine_id);
        $this->subroutine_id = $subroutine_id;

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

    /**
     * @deprecated
     */
    public function analyzeBlock(Block $block, $state)
    {
        echo Color::set(sprintf("\n0x%x:\n", $block->id), 'bold+underline');

        // Form instruction
        foreach($block->instructions as $inst) {
            echo "\t";
            echo Color::set(sprintf("0x%x: ", $inst->address), 'yellow');
            echo Color::set(sprintf("%s\n", $inst->toString()), 'blue');

            $mne = null;
            switch ($inst->mne) {
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
