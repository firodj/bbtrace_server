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

    public function populateFunctionBlocks()
    {
        $dirty = false;

        if (!isset($this->data->function_blocks)) {
            $this->data->function_blocks = [];
            $dirty = true;
        }

        foreach($this->trace_log->blocks as $block_id=>$block) {
            $function_id = $block['function_id'] ?? null;
            if (!$function_id) continue;

            if (!array_key_exists($function_id, $this->data->function_blocks)) {
                $this->data->function_blocks[$function_id] = [];
            }
            if (!in_array($block_id, $this->data->function_blocks[$function_id])) {
                $this->data->function_blocks[$function_id][] = $block_id;
                $dirty = true;
            }
        }

        return $dirty;
    }

    protected function assignFunctionByIngress(&$block, $force = false)
    {
        $block_id = $block['block_entry'];

        // check by ingress (via jmp and jcxx)
        if (array_key_exists($block_id, $this->data->ingress)) {

            $befores = array_keys( $this->data->ingress[$block_id] );

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
        $mem_refs = [];

        $inst = $this->disasmBlock($block);

        foreach($inst as $ins) {
            if (!in_array($ins->mnemonic, ['mov', 'push'])) continue;

            $x86 = &$ins->detail->x86;
            foreach($x86->operands as $op) {
                if ($op->type === 'imm' && !in_array('write', $op->access)) {

                    $rva = $this->pe_parser->va2rva($op->imm);
                    if (isset($mem_refs[$op->imm])) continue;

                    $s = $this->pe_parser->findSection($rva);

                    if (isset($s)) {
                        $section = $this->pe_parser->getSection($s->n);
                        $dest = Block::find($op->imm);
                        if ($dest) {
                            $mem_refs[$op->imm] = self::XREF_TRACE;
                        }
                    }
                }
            }
        }

        $imm = count($ins->detail->x86->operands) > 0 && $ins->detail->x86->operands[0]->type == 'imm' ? $ins->detail->x86->operands[0]->imm : null;

        $block->jump_addr     = $ins->address;
        $block->jump_mnemonic = $ins->mnemonic;
        $block->jump_operand  = $imm;

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

    public function disasm($block_id)
    {
        $img_base = $this->pe_parser->getHeaderValue('opt.ImageBase');
        if (!isset($this->trace_log->blocks[$block_id])) return;

        $block = $this->trace_log->blocks[$block_id];
        $ref = $block['module_start_ref'];

        if ($ref != $img_base) return;

        $start = $block['block_entry'];
        $end = $block['block_end'];
        $rva = $start - $ref;
        $sz = $end - $start;

        $data = $this->pe_parser->getBinaryByRva($rva, $sz);

        $insn = cs_disasm($this->capstone, $data, $start);
        return $insn;
    }

    public function print_disasm($block_id, $detail)
    {
        $insn = $this->disasm($block_id);
        if (! $insn) return;

        foreach($insn as $ins) {
            $this->print_ins($ins, $detail);

            if ($detail) {
                $this->print_x86_detail($ins->detail->x86, $detail);
            }
            fprintf(STDERR, "\n");
        }

        $block = $this->trace_log->blocks[$block_id];
        if (isset($block['function_id'])) {
            $function_id = $block['function_id'];
            $function = $this->trace_log->functions[$function_id];
            fprintf(STDERR, "function_id: %X, end: %X\n", $function_id, $function['function_end']);
            fprintf(STDERR, "function_name: %s\n", $function['function_name']);
        }
        foreach($this->data->ingress[$block_id] as $ingress => $code) {
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

    protected function calculateStack($last_block_id, $block_id, &$stacks)
    {
        // stacts Trace
        $mnemonic = null;

        $last_symbol = null;
        $last_block = null;

        $target = null;

        if (array_key_exists($last_block_id, $this->trace_log->symbols)) {
            $last_symbol = $this->trace_log->symbols[$last_block_id];
        } else if (array_key_exists($last_block_id, $this->trace_log->blocks)) {
            $last_block = $this->trace_log->blocks[$last_block_id];
            $mnemonic = $last_block['jump']->mnemonic;
            $target = $last_block['jump']->target;
        }

        $block = null;
        $symbol = null;

        if (array_key_exists($block_id, $this->trace_log->symbols)) {
            $symbol = $this->trace_log->symbols[$block_id];
            if (isset($last_symbol)) {
                $mnemonic = 'call';
            }
        } else if (array_key_exists($block_id, $this->trace_log->blocks)) {
            $block = $this->trace_log->blocks[$block_id];
            if (isset($last_symbol)) {
                $mnemonic = 'ret'; // or maybe 'call'
            }
        }

        // Info
        //fprintf(STDERR, "%X\n", $last_block_id);

        // Detect stack
        $dirty = false;

        switch ($mnemonic) {
            case 'call':
                array_push($stacks, $last_block_id);
                if (isset($last_symbol)) {
                    //fprintf(STDERR, "push stack: %X -(%s)-> %X, symbol: %s\n", $last_block_id, $mnemonic, $block_id, $last_symbol['symbol_name']);
                }
            default:
                if ($target == $block_id) {
                    $this->data->ingress[$block_id][$last_block_id] = self::XREF_EXACT;
                    $dirty = true;
                } else if(isset($last_block) && $last_block['block_end'] == $block_id) { // Jcc not taken
                    $this->data->ingress[$block_id][$last_block_id] = self::XREF_EXACT;
                    $dirty = true;
                }
                break;
            case 'ret':
                $stacks_fit = false;
                $pop_symbols = [];

                // ret from symbol/block to block 
                // stack unwind
                for($j=count($stacks); $j; $j--) {
                    $pop_block_id = $stacks[$j-1];
                    if (array_key_exists($pop_block_id, $this->trace_log->blocks)) {
                        $pop_block = &$this->trace_log->blocks[$pop_block_id];
                        if ($pop_block['block_end'] == $block_id) {
                            $stacks_fit = true;
                            array_splice($stacks, $j-1);
                            // if top-stack is block and exactly
                            if ($j == (count($stacks)-count($pop_symbols))) {
                                $this->data->ingress[$block_id][$last_block_id] = self::XREF_EXACT;
                                if (isset($pop_block['function_id']) && !isset($block['function_id'])) {
                                    $block['function_id'] = $pop_block['function_id'];
                                }
                                $dirty = true;
                            }
                            //fprintf(STDERR, "pop stack: %X -(%s)-> %X, symbol: %s\n", $last_block_id, $mnemonic, $block_id, $pop_symbol['symbol_name']);
                            break;
                        }
                    }

                    if (array_key_exists($pop_block_id, $this->trace_log->symbols)) {
                        $pop_symbols[] = $this->trace_log->symbols[$pop_block_id];
                    }
                }

                if (! $stacks_fit) {
                    $j=count($stacks);
                    $top_block_id = $stacks[$j-1];

                    // symbol to block(ret): callback
                    if (isset($last_symbol)) {
                        array_push($stacks, $last_symbol['symbol_entry']);
                        $this->data->ingress[$block_id][$last_block_id] = self::XREF_SYMRET;
                        //fprintf(STDERR, "Invalid stack: %X -(%s)-> %X, push symbol: %s\n", $last_block_id, $mnemonic, $block_id, $last_symbol['symbol_name']);
                    }

                    // block to block(ret) must be called within symbol(top stack)
                    if (isset($last_block)) {
                        if (! array_key_exists($top_block_id, $this->trace_log->symbols)) {
                            if (! array_key_exists($block_id, $this->data->callbacks)) {
                                $this->data->ingress[$block_id][$last_block_id] = self::XREF_FAKERET;
                                //fprintf(STDERR, "Invalid stack: %X -(%s)->%X, fake RET/jump top: %X\n", $last_block_id, $mnemonic, $block_id, $top_block_id);
                                $this->data->callbacks[$block_id] = self::XREF_FAKERET;
                                $dirty = true;
                            }
                        }
                    }
                }

                break;
        }

        return $dirty;
    }

    public function doAssignXref()
    {
        if (!empty($this->ingress)) return;

        $last_block_ids = [];
        $dirty = false;

        $thread_stacks = [];

        $this->trace_log->parseLog(0, null,
        function($header, $raw_data) use (&$last_block_ids, &$dirty, &$thread_stacks)
            {

            fprintf(STDERR, "Packet #%d thread:%x\n", $header['pkt_no'], $header['thread']);
            $thread_id = $header['thread'];

            if (! array_key_exists($thread_id, $last_block_ids)) {
                $last_block_ids[$thread_id] = null;
            }
            if (! array_key_exists($thread_id, $thread_stacks)) {
                $thread_stacks[$thread_id] = [];
            }

            $this->data->log_states[ $header['pkt_no'] ] = [
                'last_block_id' => $last_block_ids[$thread_id],
                'stacks' => $thread_stacks[$thread_id],
            ];
            $dirty = true;

            $data = unpack('V*', $raw_data);

            foreach($data as $block_id) {
                $last_block_id = $last_block_ids[$thread_id];
                $last_block_ids[$thread_id] = $block_id;

                if (is_null($last_block_id)) continue;

                // do Xrefs
                if (!array_key_exists($block_id, $this->data->ingress)) {
                    $this->data->ingress[ $block_id ] = [];
                }
                if (!array_key_exists($last_block_id, $this->data->ingress[$block_id])) {
                    $dirty = true;
                    $this->data->ingress[$block_id][$last_block_id] = self::XREF_TRACE;
                }

                // stacts Trace
                $dirty |= $this->calculateStack($last_block_id, $block_id, $thread_stacks[$thread_id]);
            }

            // SKIP: testing
            //if ($header['pkt_no'] >= 2)
            //return true;
        });

        $this->buildExgress();

        return $dirty;
    }

    public function buildExgress()
    {
        foreach($this->data->ingress as $block_id => $befores) {
            foreach($befores as $last_block_id => $xref_value) {
                if (!array_key_exists($last_block_id, $this->data->exgress)) {
                    $this->data->exgress[ $last_block_id ] = [];
                }
                $this->data->exgress[$last_block_id][$block_id] = $xref_value;
            }
        }
    }

    public function getBlock($id, $detail = true)
    {
        $block = null;

        if (array_key_exists($id, $this->trace_log->blocks)) {
            $block = $this->trace_log->blocks[$id];
            $block['id'] = $id;
            $block['type'] = 'block';
            $block['function'] = $this->trace_log->functions[ $block['function_id'] ?? null ] ?? null;

            if ($detail) {
                $block['disasm'] = $this->disasm($id);
            }
        }
        if (array_key_exists($id, $this->trace_log->symbols)) {
            $block = $this->trace_log->symbols[$id];
            $block['id'] = $id;
            $block['type'] = 'symbol';
        }

        if (!$block) return;
        if (!$detail) return $block;

        $ingress = $this->data->ingress[$id] ?? [];
        $exgress = $this->data->exgress[$id] ?? [];

        $block['ingress'] = array_map(function($in_id, $xref) {
            $in = $this->getBlock($in_id, false);
            $in['xref'] = $xref;
            return $in;
        }, array_keys($ingress), $ingress);

        $block['exgress'] = array_map(function($ex_id, $xref) {
            $ex = $this->getBlock($ex_id, false);
            $ex['xref'] = $xref;
            return $ex;
        }, array_keys($exgress), $exgress);
        return $block;
    }

    public function getFunction($id)
    {
        if (array_key_exists($id, $this->trace_log->functions)) {
            $block = $this->trace_log->functions[$id];
            $block['id'] = $id;
            $block['type'] = 'function';
            $block['blocks'] = array_map(function($block_id) {
                return $this->getBlock($block_id, false);
            }, $this->data->function_blocks[$id] ?? []);
            return $block;
        }
    }

    public function experiment()
    {
        $old = (object)[
            'last_block_id' => null,
            'stacks' => [],
        ];

        dump(array_map(function ($id)
            {
                return $this->trace_log->blocks[$id];
            },
            array_keys(array_filter($this->data->callbacks, function ($v, $k)
            {
                return $v == 2;
            }, ARRAY_FILTER_USE_BOTH)))
        );

        $this->trace_log->parseLog(0, null, function($header, $raw_data) use (&$old)
        {
            $last_block_id = $this->data->log_states[ $header['pkt_no'] ]['last_block_id'];
            $stacks =  $this->data->log_states[ $header['pkt_no'] ]['stacks'];

            if ($last_block_id !== $old->last_block_id) {
                printf('1 not match!');
            }
            if ($stacks !== $old->stacks) {
                printf('2 not match!');
            }

            $data = unpack('V*', $raw_data);

            foreach($data as $block_id) {
                if ($last_block_id) {
                    $dirty = $this->calculateStack($last_block_id, $block_id, $stacks);
                }
                $last_block_id = $block_id;
            }

            $old->stacks = $stacks;
            $old->last_block_id = $last_block_id;
        });

    }

}
