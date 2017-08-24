<?php

namespace App;

use Exception;

class BbAnalyzer
{
    public $function_blocks;

    public $file_name;
    public $trace_log;
    public $pe_parser;

    public $imports; // FIXME: unused
    public $exceptions; // FIXME: unused
    public $ingress;

    private $data;

    const XREF_TRACE = 0;
    const XREF_EXACT = 1;
    const XREF_SYMRET = 2;
    const XREF_FAKERET = 3;
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

        $this->openTraceLog();
        $this->openPeParser();
    }

    public function openTraceLog()
    {
        $fname = bbtrace_name($this->file_name, 'trace_log.dump');
        if (file_exists($fname)) {
            $this->trace_log = unserialize(file_get_contents($fname));
            app('log')->debug("Load TraceLog: $fname\n");
            return;
        }

        $this->trace_log = new TraceLog($this->file_name);
        $this->trace_log->buildPaging();
        file_put_contents($fname, serialize($this->trace_log));
        app('log')->debug("New TraceLog: $fname\n");
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
        $fpath = bbtrace_name($this->file_name, 'log.func');
        return (new JsonParser($fpath))->parse(function($o)
        {
            $this->saveInfo($o);
        });
    }

    protected function saveInfo($o)
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

        if (isset($o['module_start'])) {
            Module::firstOrCreate([
                'id' => $o['module_start'],
            ], [
                'entry' => $o['module_entry'],
                'end' => $o['module_end'],
                'name' => $o['module_name'],
                'path' => $o['module_path'],
            ]);
        } elseif (isset($o['block_entry'])) {
            Block::firstOrCreate([
                'id' => $o['block_entry'],
            ], [
                'end' => $o['block_end'],
                'module_id' => $o['module_start_ref'],
            ]);
        }
        elseif (isset($o['symbol_entry'])) {
            Symbol::firstOrCreate([
                'id' => $o['symbol_entry'],
            ], [
                'name' => $o['symbol_name'],
                'ordinal' => $o['symbol_ordinal'],
                'module_id' => $o['module_start_ref'],
            ]);
        }
        elseif (isset($o['function_entry'])) {
            $subroutine = Subroutine::find($o['function_entry']);
            if ($subroutine) {
                $subroutine->name = $o['function_name'];
            } else {
                $subroutine = new Subroutine;
                $subroutine->fill([
                    'id' => $o['function_entry'],
                    'name' => $o['function_name'],
                    'end' => $o['function_end'],
                    'module_id' => $o['module_start_ref'],
                ]);
            }
            if (!$subroutine->save()) {
                ;
            }
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
        $insn = cs_disasm($this->capstone, $data, $block->id);
        return $insn;
    }

    /**
     * @return Block | null
     */
    public function getStartBlock()
    {
        $base = $this->pe_parser->getHeaderValue('opt.ImageBase');
        $ep = $this->pe_parser->getHeaderValue('opt.AddressOfEntryPoint');
        return Block::find($base + $ep);
    }

    public function analyzeBlock(Block $block)
    {
        $insn = $this->disasmBlock($block);

        foreach($insn as $ins) {
            if (!in_array($ins->mnemonic, ['mov', 'push'])) continue;

            $x86 = &$ins->detail->x86;
            foreach($x86->operands as $op) {
                if ($op->type === 'imm' && !in_array('write', $op->access)) {

                    $rva = $this->pe_parser->va2rva($op->imm);
                    $ref = Reference::where(['ref_addr' => $ins->address, 'id' => $op->imm])->first();
                    if ($ref) continue;

                    $s = $this->pe_parser->findSection($rva);

                    if (isset($s)) {
                        $section = $this->pe_parser->getSection($s->n);
                        $dest = Block::find($op->imm);
                        $ref = new Reference;
                        $ref->ref_addr = $ins->address;
                        $ref->id = $op->imm;
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
            if ($block->module_id != $base) continue;

            $this->analyzeBlock($block);
        }
    }

    public function loadAll()
    {
        $this->blocks = [];
        Block::get()->each(function ($block) {
            $this->blocks[$block->id] = (object) $block->toArray();
        });

        $this->symbols = [];
        Symbol::get()->each(function ($symbol) {
            $this->symbols[$symbol->id] = (object) $symbol->toArray();
        });

        $this->ingress = [];
        Flow::get()->each(function ($flow) {
            $this->ingress += [$flow->id => []];
            $this->ingress[$flow->id][$flow->last_block_id] = $flow->xref;
        });
    }

    public function storeStates($pkt_no, $states)
    {
        if (!isset($states)) return;

        foreach(array_keys($states->last_block_id) as $thread_id) {
            TraceLogState::firstOrCreate(
                ['pkt_no' => $pkt_no, 'thread' => $thread_id],
                [
                    'last_block_id' => $states->last_block_id[$thread_id],
                    'stacks' => $states->stacks[$thread_id]
                ]);

        }
    }

    public function storeFlows(&$states)
    {
        foreach(array_values($states->flows) as $flows) {
            foreach($flows as $flow) {
                Flow::firstOrCreate([
                        'id' => $flow->block_id,
                        'last_block_id' => $flow->last_block_id
                    ],
                    [
                        'xref' => $flow->xref
                    ]
                );
            }
        }

        $states->flows[] = [];
    }

    public function prepareStates()
    {
        $states = (object)[
            'last_block_id' => [],
            'stacks' => [],
            'flows' => [],
        ];

        return $states;
    }

    public function buildIngress($chunk, $states)
    {
        $thread_id = $chunk->header->thread;
        fprintf(STDERR, "Packet #%d thread:%x\n", $chunk->header->pkt_no, $thread_id);

        $states->last_block_id += [$thread_id => null];
        $states->stacks        += [$thread_id => []];
        $states->flows         += [$thread_id => []];

        $data = unpack('V*', $chunk->raw_data);

        foreach($data as $pos=>$block_id) {
            $last_block_id = $states->last_block_id[$thread_id];
            $states->last_block_id[$thread_id] = $block_id;

            if (is_null($last_block_id)) continue;

            // do Xrefs
            if (!array_key_exists($block_id, $this->ingress)) {
                $this->ingress[$block_id] = [];
            }

            $xref = self::XREF_TRACE;
            $last_block = $this->blocks[$last_block_id] ?? null;
            if ($last_block) {
                if ($last_block->jump_dest == $block_id) { // Jxx taken or call
                    $xref = self::XREF_EXACT;
                } else if ($last_block->end == $block_id) { // Jcc not taken
                    $xref = self::XREF_EXACT;
                }
            }

            if (!array_key_exists($last_block_id, $this->ingress[$block_id])) {
                $this->ingress[$block_id][$last_block_id] = $xref;
                $states->flows[$thread_id][] = (object) [
                    'block_id' => $block_id,
                    'last_block_id' => $last_block_id,
                    'xref' => $xref
                ];
            }
        }

        return $states;
    }

    /**
     * DEPRECATED
     */
    protected function calculateStack($last_block_id, $block_id, &$stacks)
    {
        // stacts Trace
        $last_symbol = $this->symbols[$last_block_id] ?? null;
        $last_block = $this->blocks[$last_block_id] ?? null;

        if ($last_block) {
            $mnemonic = $last_block->jump_mnemonic;
            $target   = $last_block->jump_dest;
        } else {
            $mnemonic = null;
            $target = null;
        }

        $symbol = $this->symbols[$block_id] ?? null;
        $block  = $this->blocks[$block_id] ?? null;

        if ($last_symbol) {
            if ($symbol) {
                $mnemonic = 'call';
            } else if ($block) {
                $mnemonic = 'ret'; // or maybe 'call'
            }
        }

        // Info
        //fprintf(STDERR, "%X\n", $last_block_id);

        // Detect stack
        switch ($mnemonic) {
            case 'call':
                array_push($stacks, $last_block_id);
                if ($last_symbol) {
                    //fprintf(STDERR, "push stack: %X -(%s)-> %X, symbol: %s\n", $last_block_id, $mnemonic, $block_id, $last_symbol->name);
                }
            default:
                if ($target == $block_id) {
                    return self::XREF_EXACT;
                } else if ($last_block && $last_block->end == $block_id) { // Jcc not taken
                    return self::XREF_EXACT;
                }
                break;

            case 'ret':
                $stacks_fit = false;
                $pop_symbols = [];

                // ret from symbol/block to block 
                // stack unwind
                for($j=count($stacks); $j; $j--) {
                    $pop_block_id = $stacks[$j-1];
                    $pop_block = $this->blocks[$pop_block_id] ?? null;

                    if ($pop_block && $pop_block->end == $block_id) {
                        $stacks_fit = true;
                        array_splice($stacks, $j-1);
                        // if top-stack is block and exactly
                        if ($j == (count($stacks)-count($pop_symbols))) {
                            if (isset($pop_block->subroutine_id) && !isset($block->subroutine_id)) {
                                $block->subroutine_id = $pop_block->subroutine_id;
                                $this->blocks[$block->id] = $block;

                                Block::where('id', $block->id)->update(['subroutine_id' => $block->subroutine_id]);
                                fprintf(STDERR, 'Assign blocks %X -> subroutine %X\n', $block->id, $block->subroutine_id);
                            }
                            return self::XREF_EXACT;
                        }
                        //fprintf(STDERR, "pop stack: %X -(%s)-> %X, symbols: %d\n", $last_block_id, $mnemonic, $block_id, count($pop_symbols));
                        break;
                    }

                    $pop_symbol = $this->symbols[$pop_block_id] ?? null;
                    if ($pop_symbol) {
                        $pop_symbols[] = $pop_symbol; // FIXME: what to?
                    }
                }

                if (! $stacks_fit) {
                    $j = count($stacks);
                    $top_block_id = $stacks[$j-1];

                    // symbol to block(ret): callback
                    if ($last_symbol) {
                        array_push($stacks, $last_symbol->id);
                        return self::XREF_SYMRET;
                        //fprintf(STDERR, "Invalid stack: %X -(%s)-> %X, push symbol: %s\n", $last_block_id, $mnemonic, $block_id, $last_symbol->name);
                    }

                    // block to block(ret) must be called within symbol(top stack)
                    if ($last_block) {
                        $top_symbol = $this->symbols[$top_block_id] ?? null;

                        if (!$top_symbol) {
                            $ref = Reference::where('id', $block_id)->count();
                            if (!$ref) {
                                return self::XREF_FAKERET;
                                //fprintf(STDERR, "Invalid stack: %X -(%s)->%X, fake RET/jump top: %X\n", $last_block_id, $mnemonic, $block_id, $top_block_id);
                            }
                        }
                    }
                }
                break;
        }

        return self::XREF_TRACE;
    }

    /**
     * DEPRECATED
     */
    public function buildRanges()
    {
        if (!isset($this->headers)) {
            $this->headers = [];
            foreach($this->pe_parser->headers as $name => $header) {
                if (!array_key_exists($header[0], $this->headers)) {
                    $this->headers[ $header[0] ] = [];
                }
                $this->headers[ $header[0] ][$name] = $header;
            }

            $this->header_ranges = array_keys($this->headers);
            sort($this->header_ranges, SORT_NUMERIC);
        }

        if (!isset($this->function_ranges)) {
            $this->function_ranges = array_keys($this->trace_log->functions);
            sort($this->function_ranges, SORT_NUMERIC);
        }

        if (!isset($this->block_adjacents)) {
            $this->block_adjacents = [];
            foreach($this->trace_log->blocks as $block_id => &$block) {
                $this->block_adjacents[ $block['block_end'] ] = $block_id;
            }
        }
    }

    protected function createSubroutineByBlock($block, $prefix)
    {
        $subroutine = Subroutine::firstOrCreate([
            'id' => $block->id,
        ], [
            'end' => $block->end,
            'module_id' => $block->module_id,
            'name' => $prefix . '_' . dechex($block->id),
        ]);

        fprintf(STDERR, "New Function %X: %s\n", $subroutine->id, $subroutine->name);

        return $subroutine;
    }

    protected function assignSubroutineByFlow($block)
    {
        $pending_blocks = [$block];
        $subroutine_id = $block->subroutine_id;

        while ($block = array_shift($pending_blocks)) {
            if ($block->jump_mnemonic[0] == 'j') { // jxx
                $block->nextFlows->each(function($next_flow) use (&$pending_blocks, $subroutine_id) {
                    if ($next_flow->block) {
                        if ($next_flow->block->subroutine_id) {
                            fprintf(STDERR, "Block %X Jump to known %X (%X)\n", $next_flow->last_block_id, $next_flow->block->id, $next_flow->block->subroutine_id);
                        } else {
                            $pending_blocks[] = $next_flow->block;

                            $next_flow->block->subroutine_id = $subroutine_id;
                            $next_flow->block->save();

                            // Update cache.
                            $this->blocks[$next_flow->block->id] = (object) $next_flow->block->toArray();

                            fprintf(STDERR, "Assign by jump: %X (%X)\n", $next_flow->block->id, $subroutine_id);
                        }
                    }
                });
            }
        }
    }

    public function assignSubroutines()
    {
        // Filter by Subroutine Ranges
        Subroutine::get()->each(function($subroutine) {
            Block::whereBetween('id', [$subroutine->id, $subroutine->end-1])->update(['subroutine_id' => $subroutine->id]);
        });

        // Call
        Block::whereNull('subroutine_id')->get()->each(function($block) {
            $block->flows->each(function($flow) use(&$block) {
                if ($flow->lastBlock && $flow->lastBlock->subroutine_id &&
                    $flow->lastBlock->jump_mnemonic == 'call') {
                    $subroutine = $this->createSubroutineByBlock($block, 'proc');
                    $block->subroutine_id = $subroutine->id;
                    $block->save();

                    // Update cache.
                    $this->blocks[$block->id] = (object)$block->toArray();

                    $this->assignSubroutineByFlow($block);
                }
            });
        });

        // Ret
        Block::whereNull('subroutine_id')->get()->each(function($block) {
            $block->flows->each(function($flow) use(&$block) {
                if ($flow->lastBlock && $flow->lastBlock->jump_mnemonic == 'ret') {
                    $before_block = Block::where('end', $block->id)->first();
                    if ($before_block && $before_block->subroutine_id) {
                        $block->subroutine_id = $before_block->subroutine_id;
                        $block->save();

                        fprintf(STDERR, "Assign by return %X (%X)\n", $block->id, $block->subroutine_id);

                        // Update cache.
                        $this->blocks[$block->id] = (object)$block->toArray();

                        $this->assignSubroutineByFlow($block);
                    } else if (($block->id & 0xf) == 0) { // align 10h
                        // GUEST!
                        $subroutine = $this->createSubroutineByBlock($block, 'callback');
                        $block->subroutine_id = $subroutine->id;
                        $block->save();

                        fprintf(STDERR, "Assign by callback %X (%X)\n", $block->id, $block->subroutine_id);

                        // Update cache.
                        $this->blocks[$block->id] = (object)$block->toArray();

                        $this->assignSubroutineByFlow($block);
                    }
                }
            });
        });

        // Jxx
        Block::whereNull('subroutine_id')->get()->each(function($block) {
            $block->flows->each(function($flow) use(&$block) {
                if ($flow->lastBlock && $flow->lastBlock->subroutine_id &&
                    $flow->lastBlock->jump_mnemonic[0] == 'j') {
                        $block->subroutine_id = $flow->lastBlock->subroutine_id;
                        $block->save();

                        fprintf(STDERR, "Assign by jump %X (%X)\n", $block->id, $block->subroutine_id);

                        // Update cache.
                        $this->blocks[$block->id] = (object)$block->toArray();

                        $this->assignSubroutineByFlow($block);
                    }
            });
        });

        // Symbol
        Block::whereNull('subroutine_id')->get()->each(function($block) {
            $block->flows->each(function($flow) use(&$block) {
                if ($flow->lastSymbol) {
                    $before_block = Block::where('end', $block->id)->first();
                    if ($before_block) {
                        if ($before_block->subroutine_id) {
                            $block->subroutine_id = $before_block->subroutine_id;
                            $block->save();

                            fprintf(STDERR, "Assign by last symbol (return) %X (%X)\n", $block->id, $block->subroutine_id);

                            // Update cache.
                            $this->blocks[$block->id] = (object)$block->toArray();

                            $this->assignSubroutineByFlow($block);
                        }
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



    public function buildExgress()
    {
        foreach($this->ingress as $block_id => $befores) {
            foreach($befores as $last_block_id => $xref_value) {
                if (!array_key_exists($last_block_id, $this->data->exgress)) {
                    $this->data->exgress[ $last_block_id ] = [];
                }
                $this->data->exgress[$last_block_id][$block_id] = $xref_value;
            }
        }
    }

}
