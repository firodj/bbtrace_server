<?php

namespace App\Services;

use Exception;
use App\Module;
use App\Block;
use App\Instruction;
use App\Operand;
use App\Flow;
use App\Symbol;
use App\Subroutine;
use App\Reference;
use App\Expression;

class BbAnalyzer
{
    public $function_blocks;

    public $file_name;
    public $pe_parser;

    public $imports; // FIXME: unused
    public $exceptions; // FIXME: unused
    public $ingress;

    private $data;

    const XREF_TRACE = 0;
    const XREF_EXACT = 1;
    const XREF_SPLIT = 2;

    const JUMP_MNEMONICS = ['jmp', 'jg', 'jge', 'je', 'jne', 'js', 'jns', 'ja', 'jb', 'jl', 'jle'];

    public function __construct($file_name)
    {
        $this->file_name = realpath($file_name);
        $this->imports = [];
        $this->exceptions = [];
        $this->ingress = [];

        $this->initialize();
    }

    public function getName()
    {
        return basename($this->file_name);
    }

    public function initialize()
    {
        $this->capstone = cs_open(CS_ARCH_X86, CS_MODE_32);
        cs_option($this->capstone, CS_OPT_DETAIL, CS_OPT_ON);

        $this->openPeParser();
    }

    public function openPeParser()
    {
        $fname = bbtrace_name($this->file_name, 'pe_parser.dump');
        if (file_exists($fname)) {
            $this->pe_parser = unserialize(file_get_contents($fname));
            app('log')->debug("Load PeParser: $fname\n");
            return;
        }

        $this->pe_parser = new PeParser($this->file_name);
        $this->pe_parser->parsePe();
        file_put_contents($fname, serialize($this->pe_parser));
        app('log')->debug("New PeParser: $fname\n");
    }

    public function parseInfo()
    {
        $fpath = bbtrace_name($this->file_name, 'log.info');
        return (new JsonParser($fpath))->parse(function($o)
        {
            $this->saveInfo($o);
        });
    }

    public function parseFunc()
    {
        $subroutines = [];

        $fpath = bbtrace_name($this->file_name, 'log.func');

        (new JsonParser($fpath))->parse(function($o) use (&$subroutines)
        {
            $o = $this->convertAddressField($o);

            if (isset($o['function_entry'])) {
                $subroutines[$o['function_entry']] = $o;
            }
        });

        // $addresses = array_keys($subroutines);
        // sort($addresses);

        Block::orderBy('id')->get()->each(function ($block) use (&$subroutines) {
            if (array_key_exists($block->addr, $subroutines)) {
                $o = $subroutines[$block->addr];
                $subroutine = $this->saveInfo($o);
                if ($subroutine instanceof Subroutine) {
                    $block->subroutine_id = $subroutine->id;
                    $block->save();
                }
            }
        });
    }

    protected function convertAddressField($o)
    {
        foreach(['block_entry', 'block_end', 'symbol_entry',
            'module_start_ref', 'module_start', 'module_end', 'module_entry',
            'exception_code', 'exception_address', 'fault_address',
            'function_entry', 'function_end',
        ] as $k) {
            if (isset($o[$k]) && is_string($o[$k]) && strpos($o[$k], '0x') === 0) {
                $o[$k] = hexdec($o[$k]);
            }
        }

        return $o;
    }

    protected function saveInfo($o)
    {
        $o = $this->convertAddressField($o);

        if (isset($o['module_start'])) {
            $module = Module::where('addr', $o['module_start'])->first();
            if (! $module) {
                $module = new Module;
                $module->fill([
                    'addr' => $o['module_start'],
                    'entry' => $o['module_entry'],
                    'end' => $o['module_end'],
                    'name' => $o['module_name'],
                    'path' => $o['module_path'],
                ]);
                $module->save();
            }
            return $module;
        } elseif (isset($o['block_entry'])) {
            $block = Block::where('addr', $o['block_entry'])->first();
            if (! $block) {
                $module = Module::where('addr', $o['module_start_ref'])->firstOrFail();
                $block = new Block;
                $block->fill([
                    'addr' => $o['block_entry'],
                    'end' => $o['block_end'],
                    'module_id' => $module->id,
                ]);
                $block->save();
            }
            return $block;
        }
        elseif (isset($o['symbol_entry'])) {
            $symbol = Symbol::where('addr', $o['symbol_entry'])->first();
            if (! $symbol) {
                $module = Module::where('addr', $o['module_start_ref'])->firstOrFail();
                $symbol = new Symbol;
                $symbol->fill([
                    'addr' => $o['symbol_entry'],
                    'name' => $o['symbol_name'],
                    'ordinal' => $o['symbol_ordinal'],
                    'module_id' => $module->id,
                ]);
                $symbol->save();
            }
            return $symbol;
        }
        elseif (isset($o['function_entry'])) {
            $subroutine = Subroutine::where('addr', $o['function_entry'])->first();
            if ($subroutine) {
                $subroutine->name = $o['function_name'];
            } else {
                $module = Module::where('addr', '<=', $o['module_start_ref'])
                                ->where('end', '>', $o['module_start_ref'])
                                ->firstOrFail();
                $subroutine = new Subroutine;
                $subroutine->fill([
                    'addr' => $o['function_entry'],
                    'name' => $o['function_name'],
                    'end' => $o['function_end'],
                    'module_id' => $module->id,
                ]);
            }
            $subroutine->save();

            return $subroutine;
        }
        elseif (isset($o['exception_code'])) {
            $this->exceptions[ $o['exception_address'] ] = $o;
        }
        elseif (isset($o['import_module_name'])) {
            $this->imports[ $o['symbol_name'] ] = $o;
        }
        else {
            fprintf(STDERR, "Bad Info:%s\n", json_encode($o));
        }
    }

    public function disasmBlock(Block $block)
    {
        $data = $this->pe_parser->getBinaryByRva($block->getRva(), $block->getSize());
        $insn = cs_disasm($this->capstone, $data, $block->addr);

        foreach ($insn as $ins) {
            $inst = Instruction::where('addr', $ins->address)->first();

            if (! $inst) {
                $inst = new Instruction;
                $inst->block_id = $block->id;
                $inst->mne = $ins->mnemonic;
                $inst->addr = $ins->address;
                $inst->end = $ins->address + count($ins->bytes);
                $inst->save();

                $detail = $ins->detail->x86;

                foreach($detail->operands as $i => $opr) {
                    $opnd = new Operand;
                    $opnd->pos = $i;
                    $opnd->size = $opr->size * 8;
                    $opnd->type = $opr->type;

                    switch($opr->type) {
                    case "reg":
                        $opnd->reg = $opr->reg;
                        break;

                    case "imm":
                        $opnd->imm = $opr->imm;
                        break;

                    case "mem":
                        $opnd->reg = $opr->mem->base;
                        $opnd->index = $opr->mem->index;
                        $opnd->scale = $opr->mem->scale;
                        $opnd->imm = $opr->mem->disp;
                        $opnd->seg = $opr->mem->segment;
                        $opnd->memNormalize();
                        break;

                    default:
                        throw new Exception('Unknown operand type ' . $opr->type);
                    }

                    $inst->operands()->save($opnd);

                    Expression::createExpressionFromOperand($opnd);
                }
            }
        }

        return count($insn);
    }

    /**
     * @return Block | null
     */
    public function getStartBlock()
    {
        $base = $this->pe_parser->getHeaderValue('opt.ImageBase');
        $ep = $this->pe_parser->getHeaderValue('opt.AddressOfEntryPoint');
        return Block::where('addr', $base + $ep)->first();
    }

    public function analyzeBlock(Block $block)
    {
        $insn = $this->disasmBlock($block);

        foreach($insn as $ins) {
            //if (!in_array($ins->mnemonic, ['mov', 'push', 'call'])) continue;

            $x86 = &$ins->detail->x86;
            foreach($x86->operands as $op) {
                $addr = null;
                if ($op->type === 'imm') { // && !in_array('write', $op->access)) {
                    $addr = $op->imm;
                }
                if ($op->type == 'mem') {
                    if ($op->mem->base == 0 && $op->mem->index == 0 &&
                        $op->mem->segment == 0 && $op->mem->scale == 1) {
                        $addr = $op->mem->disp;
                    }
                }
                if ($addr) {
                    $rva = $this->pe_parser->va2rva($addr);
                    $ref = Reference::where(['ref_addr' => $ins->address, 'addr' => $addr])->first();
                    if ($ref) continue;

                    $s = $this->pe_parser->findSection($rva);

                    if (isset($s)) {
                        $section = $this->pe_parser->getSection($s->n);
                        $dest = Block::where('addr', $addr)->first();

                        $ref = new Reference;
                        $ref->ref_addr = $ins->address;
                        $ref->addr = $addr;

                        if (in_array('CODE', $section->flags)) {
                            $ref->kind = isset($dest) ? 'C' : 'X';
                        } else if (in_array('INITIALIZED_DATA', $section->flags)) {
                            $ref->kind = 'D';
                        } else if (in_array('UNINITIALIZED_DATA', $section->flags)) {
                            $ref->kind = 'V';
                        } else {
                            dump($section);
                        }
                        $ref->save();
                    }
                }
            }
        }

        $imm = count($ins->detail->x86->operands) > 0 && $ins->detail->x86->operands[0]->type == 'imm' ? $ins->detail->x86->operands[0]->imm : null;

        $block->jump_addr     = $ins->address;
        $block->jump_mnemonic = $ins->mnemonic;
        $block->jump_dest     = $ins->mnemonic != 'ret' ? $imm : null;

        $stop = $ins->address + count($ins->bytes);

        if ($stop != $block->end) {
            throw new Exception('Wrong disassmble!');
        }

        $block->save();
    }

    public function analyzeAllBlocks()
    {
        $base = $this->pe_parser->getHeaderValue('opt.ImageBase');

        foreach(Block::get() as $block) {
            if ($block->module->addr != $base) {
                printf("Block 0x%x different module 0x%x!\n", $block->addr, $base);
                continue;
            }

            $this->analyzeBlock($block);
        }
    }

    public function loadAll()
    {
        $this->blocks = [];
        Block::get()->each(function ($block) {
            $this->blocks[$block->addr] = (object) $block->toArray();
        });

        $this->symbols = [];
        Symbol::get()->each(function ($symbol) {
            $this->symbols[$symbol->addr] = (object) $symbol->toArray();
        });

        $this->ingress = [];
        Flow::get()->each(function ($flow) {
            $this->ingress += [$flow->block->addr => []];
            $this->ingress[$flow->block->addr][$flow->lastBlock->addr] = $flow->xref;
        });
    }

    public function parseFlowLog()
    {
        $fname = bbtrace_name($this->file_name, 'log.flow');
        $fp = fopen($fname, 'r');

        while (($data = fgetcsv($fp, 100, ",")) !== FALSE) {
            $block_addr = hexdec($data[0]);
            $last_block_addr = hexdec($data[1]);

            $last_block = Block::where('addr', $last_block_addr)->first();
            $xref = self::XREF_TRACE;

            if ($last_block) {
                if ($last_block->jump_dest == $block_addr) { // Jxx taken or call
                    $xref = self::XREF_EXACT;
                } else if ($last_block->end == $block_addr) { // Jcc not taken
                    $xref = self::XREF_EXACT;
                }
            }

            $block = Block::where('addr', $block_addr)->first();
            if (! $block) {
                $block = Symbol::where('addr', $block_addr)->first();
            }

            if (! $last_block) {
                $last_block = Symbol::where('addr', $last_block_addr)->first();
            }

            $flow = Flow::where('block_id', $block->id)->where('block_type', $block->getTable())
                        ->where('last_block_id', $last_block->id)->where('last_block_type', $last_block->getTable())
                        ->first();

            if (! $flow) {
                $flow = new Flow;
                $flow->fill([
                    'block_id' => $block->id,
                    'block_type' => $block->getTable(),
                    'last_block_id' => $last_block->id,
                    'last_block_type' => $last_block->getTable(),
                    'xref' => $xref
                ]);
                $flow->save();
            }
        }

        fclose($fp);
    }

    protected function createSubroutineByBlock($block, $prefix)
    {
        $subroutine = Subroutine::where('addr', $block->addr)->first();
        if (! $subroutine) {
            $subroutine = new Subroutine;
            $subroutine->fill([
                'addr' => $block->addr,
                'end' => $block->end,
                'module_id' => $block->module_id,
                'name' => $prefix . '_' . dechex($block->addr),
            ]);
            $subroutine->save();
        }

        fprintf(STDERR, "New Function %X: %s\n", $subroutine->addr, $subroutine->name);

        return $subroutine;
    }

    protected function assignSubroutineByFlow($block)
    {
        $pending_blocks = [$block];
        $subroutine_id = $block->subroutine_id;

        while ($block = array_shift($pending_blocks)) {
            foreach($block->nextFlows as $next_flow) {
                if ($block->jump_mnemonic[0] != 'j' && ($next_flow->xref != self::XREF_SPLIT)) continue;
                if (! $next_flow->block) continue;
                if (!($next_flow->block instanceof Block)) continue;

                if ($next_flow->block->subroutine_id) {
                    fprintf(STDERR, "Block %X Jump to known %X (%X)\n", $next_flow->last_block_id, $next_flow->block->id, $next_flow->block->subroutine_id);
                } else {
                    $pending_blocks[] = $next_flow->block;

                    $next_flow->block->subroutine_id = $subroutine_id;
                    $next_flow->block->save();

                    fprintf(STDERR, "Assign by jump: %X (%X)\n", $next_flow->block->id, $subroutine_id);
                }
            }
        }
    }

    public function assignSubroutines()
    {
        // Filter by Subroutine Ranges
        Subroutine::orderBy('addr')->get()->each(function($subroutine) {
            Block::whereBetween('addr', [$subroutine->addr, $subroutine->end - 1])
                 ->update(['subroutine_id' => $subroutine->id]);
        });

        // Filter whose block not within range
        // Call
        Block::whereNull('subroutine_id')->get()->each(function($block) {
            $block->flows->each(function($flow) use(&$block) {
                if ($flow->lastBlock && 
                    $flow->lastBlock instanceof Block &&
                    $flow->lastBlock->subroutine_id &&
                    $flow->lastBlock->jump_mnemonic == 'call') {

                    $subroutine = $this->createSubroutineByBlock($block, 'proc');
                    $block->subroutine_id = $subroutine->id;
                    $block->save();

                    $this->assignSubroutineByFlow($block);
                }
            });
        });

        // Ret
        Block::whereNull('subroutine_id')->get()->each(function($block) {
            $block->flows->each(function($flow) use(&$block) {
                if ($flow->lastBlock &&
                    $flow->lastBlock instanceof Block &&
                    $flow->lastBlock->jump_mnemonic == 'ret') {
                    $before_block = Block::where('end', $block->addr)->first();

                    if ($before_block && $before_block->subroutine_id) {
                        $block->subroutine_id = $before_block->subroutine_id;
                        $block->save();

                        fprintf(STDERR, "Assign by return %X (%X)\n", $block->id, $block->subroutine_id);

                        $this->assignSubroutineByFlow($block);
                    } else if (($block->addr & 0xf) == 0) { // align 10h
                        // GUEST!
                        /*
                        $subroutine = $this->createSubroutineByBlock($block, 'callback');
                        $block->subroutine_id = $subroutine->id;
                        $block->save();

                        fprintf(STDERR, "Assign by callback %X (%X)\n", $block->id, $block->subroutine_id);

                        $this->assignSubroutineByFlow($block);
                         */
                    }
                }
            });
        });

        // Jxx
        Block::whereNull('subroutine_id')->get()->each(function($block) {
            $block->flows->each(function($flow) use(&$block) {
                if ($flow->lastBlock && 
                    $flow->lastBlock instanceof Block &&
                    $flow->lastBlock->subroutine_id) {
                    if ($flow->lastBlock->jump_mnemonic[0] == 'j' || ($flow->xref == self::XREF_SPLIT)) {
                        $block->subroutine_id = $flow->lastBlock->subroutine_id;
                        $block->save();

                        fprintf(STDERR, "Assign by jump %X (%X)\n", $block->id, $block->subroutine_id);

                        $this->assignSubroutineByFlow($block);
                    }
                }
            });
        });

        // Symbol
        Block::whereNull('subroutine_id')->get()->each(function($block) {
            $block->flows->each(function($flow) use(&$block) {
                if ($flow->lastBlock && $flow->lastBlock instanceof Symbol) {
                    $before_block = Block::where('end', $block->addr)->first();
                    if ($before_block && $before_block->subroutine_id) {
                        $block->subroutine_id = $before_block->subroutine_id;
                        $block->save();

                        fprintf(STDERR, "Assign by last symbol (return) %X (%X)\n", $block->id, $block->subroutine_id);

                        $this->assignSubroutineByFlow($block);
                    }
                }
            });
        });
    }

    public function printDisasm(Block $block, $detail)
    {
        $insn = $this->disasmBlock($block);
        if (! $insn) return;

        $output = new Output();

        foreach($insn as $ins) {
            $output->print_ins($ins, $detail);

            if ($detail) {
                $output->print_x86_detail($ins->detail->x86, $detail);
            }
            fprintf(STDERR, "\n");
        }

        $function = Subroutine::find($block->id);
        if ($function) {
            fprintf(STDERR, "function_id: %X, end: %X\n", $function->id, $function->end);
            fprintf(STDERR, "function_name: %s\n", $function->name);
        }

        foreach($this->ingress[$block_id] as $ingress => $code) {
            $in_block = $this->trace_log->blocks[$ingress] ?? null;
            if ($in_block) {
                fprintf(STDERR, "- ingress: %X (%d), jump: %s", $ingress, $code, $in_block['jump']->mnemonic);
                $function = $this->trace_log->functions[ $in_block['function_id'] ?? null ] ?? null;
                if ($function) {
                    fprintf(STDERR, ", func: %X, name: %s", $function['function_entry'], $function['function_name']);
                }
                fprintf(STDERR, "\n");
            }
            $in_block = $this->trace_log->symbols[$ingress] ?? null;
            if ($in_block) {
                fprintf(STDERR, "- ingress: %X (%d), symbol: %s\n", $ingress, $code, $in_block['symbol_name']);
            }
        }
        foreach($this->data->exgress[$block_id] as $exgress => $code) {
            $ex_block = $this->trace_log->blocks[$exgress] ?? null;
            if ($ex_block) {
                fprintf(STDERR, "- exgress: %X (%d)", $exgress, $code);
                $function = $this->trace_log->functions[ $ex_block['function_id'] ?? null ] ?? null;
                if ($function) {
                    fprintf(STDERR, ", func: %X, name: %s", $function['function_entry'], $function['function_name']);
                }
                fprintf(STDERR, "\n");
            }
            $ex_block = $this->trace_log->symbols[$exgress] ?? null;
            if ($ex_block) {
                fprintf(STDERR, "- exgress: %X (%d), symbol: %s\n", $ingress, $code, $ex_block['symbol_name']);
            }
        }
        //fprintf(STDERR, "callback: %d\n", $this->data->callbacks[$block_id] ?? null);
    }

    public function fixOverlappedBlocks()
    {
        $ends = Block::select('end', app('db')->raw('count(id) as n'))
                     ->groupBy('end')
                     ->havingRaw('count(id) > 1')
                     ->orderBy('n', 'asc')
                     ->get();

        foreach($ends as $end) {
            $blocks = Block::where('end', $end->end)->orderBy('addr', 'asc')->get();

            printf("end: 0x%x, ids: %s\n", $end->end,
                implode(', ', $blocks->pluck('id')->toArray())
            );
            printf("origin: 0x%x - 0x%x\n", $blocks[0]->addr, $blocks[0]->end);
            // Check if has been applied
            foreach($blocks as $block) {
                if ($block->nextFlows()->count() == 0) {
                    throw new Exception(sprintf("Block %d with end 0x%x doesnt have nextFlows", $block->id, $block->end));
                }
            }

            // Merge all exit flags to last block
            for ($i = 0; $i < $blocks->count()-1; ++$i) {
                $flows = $blocks[$i]->nextFlows;
                foreach($flows as $flow) {
                    $end_block = $blocks->last();
                    $new_flow = Flow::where('last_block_id', $end_block->id)
                                    ->where('last_block_type', $end_block->getTable())
                                    ->where('block_id', $flow->block_id)
                                    ->where('block_type', $flow->block_type)
                                    ->first();
                    if (! $new_flow) {
                        $flow->last_block_id = $end_block->id;
                        $flow->last_block_type = $end_block->getTable();
                        $flow->save();
                        printf("Flow %d updated!\n", $flow->id);
                    } else {
                        $flow->delete();
                        printf("Flow %d deleted!\n", $flow->id);
                    }
                }
            }

            // Shrink each blocks;
            for($i = 0; $i < $blocks->count() - 1; ++$i) {
                $blocks[$i]->end = $blocks[$i + 1]->addr;
                if ($blocks[$i]->jump_addr >= $blocks[$i]->end) {
                    $blocks[$i]->jump_mnemonic = null;
                    $blocks[$i]->jump_dest = null;
                    $blocks[$i]->jump_addr = null;
                }
                $blocks[$i]->save();
                printf("Block %d updated!\n", $blocks[$i]->id);

                $flow = new Flow;
                $flow->last_block_id = $blocks[$i]->id;
                $flow->last_block_type = $blocks[$i]->getTable();
                $flow->block_id = $blocks[$i + 1]->id;
                $flow->block_type = $blocks[$i + 1]->getTable();
                $flow->xref = self::XREF_SPLIT;
                $flow->save();

                printf("Flow %d created!\n", $flow->id);
            }
            printf("Block %d untouched!\n", $blocks[$i]->id);
        }
    }
}
