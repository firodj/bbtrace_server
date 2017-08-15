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
            fprintf(STDERR, "Load TraceLog: $fname\n");
            return;
        }

        $this->trace_log = new TraceLog($this->file_name);
        $this->trace_log->buildPaging();
        file_put_contents($fname, serialize($this->trace_log));
        fprintf(STDERR, "New TraceLog: $fname\n");
    }

    public function openPeParser()
    {
        $fname = bbtrace_name($this->file_name, 'pe_parser.dump');
        if (file_exists($fname)) {
            $this->pe_parser = unserialize(file_get_contents($fname));
            fprintf(STDERR, "Load PeParser: $fname\n");
            return;
        }

        $this->pe_parser = new PeParser($this->file_name);
        $this->pe_parser->parsePe();
        file_put_contents($fname, serialize($this->pe_parser));
        fprintf(STDERR, "New PeParser: $fname\n");
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
            Subroutine::firstOrCreate([
                'id' => $o['function_entry'],
            ], [
                'name' => $o['function_name'],
                'end' => $o['function_end'],
                'module_id' => $o['module_start_ref'],
            ]);
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

    public function storeIngress()
    {
        foreach($this->ingress as $block_id => $last_block_ids) {
            foreach($last_block_ids as $last_block_id => $xref) {
                Flow::firstOrCreate(['id' => $block_id, 'last_block_id' => $last_block_id],
                    ['xref' => $xref]);
            }
        }
    }

    public function buildIngress($chunk, $states)
    {
        $thread_id = $chunk->header->thread;
        fprintf(STDERR, "Packet #%d thread:%x\n", $chunk->header->pkt_no, $thread_id);

        if (!isset($states)) {
            $states = (object)[
                'last_block_id' => [],
                'stacks' => [],
            ];
        }

        if (! array_key_exists($thread_id, $states->last_block_id)) {
            $states->last_block_id[$thread_id] = null;
        }

        if (! array_key_exists($thread_id, $states->stacks)) {
            $states->stacks[$thread_id] = [];
        }

        $data = unpack('V*', $chunk->raw_data);

        foreach($data as $pos=>$block_id) {
            $last_block_id = $states->last_block_id[$thread_id];
            $states->last_block_id[$thread_id] = $block_id;

            if (is_null($last_block_id)) continue;

            // do Xrefs
            if (!array_key_exists($block_id, $this->ingress)) {
                $this->ingress[$block_id] = [];
            }

            // stacts Trace
            $xref = $this->calculateStack($last_block_id, $block_id, $states->stacks[$thread_id]);

            if (!array_key_exists($last_block_id, $this->ingress[$block_id])) {
                $this->ingress[$block_id][$last_block_id] = $xref;
            }
        }

        return $states;
    }

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

    protected function newFunctionByBlock(&$block, $prefix)
    {
        $block_id = $block['block_entry'];

        if (!array_key_exists($block_id, $this->trace_log->functions)) {
            $function = [
                'function_entry' => $block_id,
                'function_end' => $block['block_end'], // fake it
                'function_name' => $prefix . '_' . dechex($block_id),
            ];

            $this->trace_log->functions[$block_id] = $function;

            fprintf(STDERR, "New Function %X: %s\n", $block_id, $function['function_name']);

            $this->function_ranges[] = $block_id;
            sort($this->function_ranges, SORT_NUMERIC);

            return true;
        }
    }

    protected function assignFunctionByCallback(&$block, $force = false)
    {
        $block_id = $block['block_entry'];

        // check via callback
        if (array_key_exists($block_id, $this->data->callbacks)) {
            $block['function_id'] = $block_id;
            $this->newFunctionByBlock($block, 'callback');
            fprintf(STDERR, "Assign %X func: %X\n", $block_id, $block['function_id']);
            return true;
        }

        return false;
    }

    protected function assignFunctionByIngress(&$block, $force = false)
    {
        $block_id = $block['block_entry'];

        // check by ingress (via jmp and jcxx)
        if (array_key_exists($block_id, $this->ingress)) {

            $befores = array_keys( $this->ingress[$block_id] );

            foreach($befores as $before_id) {
                if (isset($this->trace_log->blocks[$before_id])) {
                    $before = $this->trace_log->blocks[$before_id];

                    if (in_array($before['jump']->mnemonic, self::JUMP_MNEMONICS)) {
                        if (isset($before['function_id'])) {
                            $block['function_id'] = $before['function_id'];
                            fprintf(STDERR, "[FuncByIngress-Jxx] %X func: %X\n", $block_id, $block['function_id']);
                            return true;
                            break;
                        }
                    }
                    else if ($before['jump']->mnemonic == 'call') {
                        $block['function_id'] = $block_id;
                        $this->newFunctionByBlock($block, 'proc');
                        fprintf(STDERR, "[FuncByIngress-CALL] %X func: %X\n", $block_id, $block['function_id']);
                        return true;
                    } else if ($before['jump']->mnemonic == 'ret') {
                        if (!isset($block['function_id'])) {
                            $block_adjacent = $this->trace_log->blocks[ $this->block_adjacents[$block_id] ?? null ] ?? null;
                            if ($block_adjacent && isset($block_adjacent['function_id']) && $block_adjacent['jump']->mnemonic == 'call') {
                                $block['function_id'] = $block_adjacent['function_id'];
                                fprintf(STDERR, "[FuncByIngress-RET] %X, with ingress: %X\n", $block_id, $block['function_id']);
                                return true;
                            } else {
                                fprintf(STDERR, "[FuncByIngress-RET] Unknown for %X, with ingress: %X\n", $block_id, $before_id);
                            }
                        }
                    } else {
                        fprintf(STDERR, "[FuncByIngress-%s] Unknown handle jump\n", $before['jump']->mnemonic);
                    }
                } else if (isset($this->trace_log->symbols[$before_id])) {
                    $before = $this->trace_log->symbols[$before_id];
                    // NOPE:
                }
            }
        }
    }

    protected function assignFunctionByExgress(&$block, $force)
    {
        $block_id = $block['block_entry'];
        if (array_key_exists($block_id, $this->data->exgress)) {

            $afters = array_keys( $this->data->exgress[$block_id] );

            foreach($afters as $after_id) {
                if (isset($this->trace_log->blocks[$after_id])) {
                    $after = $this->trace_log->blocks[$after_id];

                    if (in_array($block['jump']->mnemonic, self::JUMP_MNEMONICS)) {
                        if (isset($after['function_id'])) {
                            $block['function_id'] = $after['function_id'];
                            fprintf(STDERR, "[FuncByExgress] %X func: %X\n", $block_id, $block['function_id']);
                            return true;
                        }
                    }
                }
            }
        }
    }

    public function assignSubroutines()
    {
        Subroutine::get()->each(function($subroutine) {
            Block::whereBetween('id', [$subroutine->id, $subroutine->end-1])->update(['subroutine_id' => $subroutine->id]);
        });
    }

    public function doAssignFunction($force = false)
    {
        $this->buildRanges();

        $dirty = false;

        foreach($this->trace_log->blocks as $block_id => &$block) {
            if (isset($block['function_id'])) {
                if (!$force) {
                    continue;
                }
            }

            $function_id = NearestValue::array_numeric_sorted_nearest($this->function_ranges,
                $block_id,
                NearestValue::ARRAY_NEAREST_LOWER);

            $function = $this->trace_log->functions[$function_id];

            if ($block_id < $function['function_end']) {
                $dirty = true;
                $block['function_id'] = $function_id;
            } else {
                $dirty |= $this->assignFunctionByCallback($block, $force);
                $dirty |= $this->assignFunctionByIngress($block, $force);
                $dirty |= $this->assignFunctionByExgress($block, $force);
            }
        }

        return $dirty;
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
