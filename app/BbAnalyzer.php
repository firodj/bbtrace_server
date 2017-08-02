<?php

namespace App;

use Exception;
use Serializable;

class BbAnalyzer implements Serializable
{
    public $fname;

    private $data;

    const XREF_TRACE = 0;
    const XREF_EXACT = 1;
    const XREF_SYMRET = 2;
    const XREF_FAKERET = 3;
    const JUMP_MNEMONICS = ['jmp', 'jg', 'jge', 'je', 'jne', 'js', 'jns', 'ja', 'jb', 'jl', 'jle'];

    public function __construct()
    {
        $this->data = (object) [
            'exgress' => [],
            'ingress' => [],
            'callbacks' => [],
            'log_states' => [],
        ];

        $this->initialize();
    }

    public function initialize()
    {
        $this->capstone = cs_open(CS_ARCH_X86, CS_MODE_32);
        cs_option($this->capstone, CS_OPT_DETAIL, CS_OPT_ON);

        $this->path_exe = dirname(env('APP_EXE'));
        $this->name_exe = basename(env('APP_EXE'));

        $this->fname_pe_parser = $this->path_exe.DIRECTORY_SEPARATOR.'bbtrace.'.$this->name_exe.'.pe_parser.dump';
        $this->fname_trace_log = $this->path_exe.DIRECTORY_SEPARATOR.'bbtrace.'.$this->name_exe.'.trace_log.dump';
        $this->fname = self::makeDumpName();

        $this->open();
    }

    public static function makeDumpName()
    {
        $path_exe = dirname(env('APP_EXE'));
        $name_exe = basename(env('APP_EXE'));
        return $path_exe.DIRECTORY_SEPARATOR.'bbtrace.'.$name_exe.'.bb_analyzer.dump';
    }

    public static function string_hex($bytes)
    {
        if (is_string($bytes)) {
            $bytes = unpack('C*', $bytes);
        }
        return implode(" ",
            array_map(function($x) {
                return is_int($x) ? sprintf("0x%02x", $x) : $x;
                },
                $bytes
            )
        );
    }

    public static function print_ins($ins, $detail)
    {
        printf("%X: %34s | %-10s", $ins->address, self::string_hex($ins->bytes), $ins->mnemonic);
        if ($ins->op_str) printf(" %s", $ins->op_str);

        if ($detail) {
            if (count($ins->detail->regs_read)) {
                printf("\tregisters read: %s\n", implode(" ", $ins->detail->regs_read));
            }
            if (count($ins->detail->regs_write)) {
                printf("\tregisters modified: %s\n", implode(" ", $ins->detail->regs_write));
            }
            if (count($ins->detail->groups)) {
                printf("\tinstructions groups: %s\n",
                    implode(" ", $ins->detail->groups));
            }
        }
    }

    public static function print_x86_detail($x86)
    {
        if ($x86->prefix) {
            printf("\tprefix: %s\n", self::string_hex($x86->prefix));
        }
        printf("\topcode: %s\n", self::string_hex($x86->opcode));

        printf("\trex: 0x%x\n", $x86->rex);

        printf("\taddr_size: %u\n", $x86->addr_size);
        printf("\tmodrm: 0x%x\n", $x86->modrm);
        printf("\tdisp: 0x%x\n", $x86->disp);

        if ($x86->sib) {
            printf("\tsib: 0x%x\n", $x86->sib);
            if ($x86->sib_base)
                printf("\t\tsib_base: %s\n", $x86->sib_base);
            if ($x86->sib_index)
                printf("\t\tsib_index: %s\n", $x86->sib_index);
            if ($x86->sib_scale)
                printf("\t\tsib_scale: %d\n", $x86->sib_scale);
        }

        // XOP code condition
        if ($x86->xop_cc) {
            printf("\tsse_cc: %u\n", $x86->xop_cc);
        }

        // SSE code condition
        if ($x86->sse_cc) {
            printf("\tsse_cc: %u\n", $x86->sse_cc);
        }

        // AVX code condition
        if ($x86->avx_cc) {
            printf("\tavx_cc: %u\n", $x86->avx_cc);
        }

        // AVX Suppress All Exception
        if ($x86->avx_sae) {
            printf("\tavx_sae: %u\n", $x86->avx_sae);
        }

        // AVX Rounding Mode
        if ($x86->avx_rm) {
            printf("\tavx_rm: %u\n", $x86->avx_rm);
        }

        printf("\teflags:\n");
        foreach($x86->eflags as $ops => $flags) {
            if ($flags) {
                printf("\t\t%s: %s\n", $ops, implode(' ', $flags));
            }
        }

        printf("\top_count: %u\n", count($x86->operands));
        foreach ($x86->operands as $i => $op) {
            switch($op->type) {
                case 'reg': // X86_OP_REG
                    printf("\t\toperands[%u].type: reg = %s\n", $i, $op->reg);
                    break;
                case 'imm': // X86_OP_IMM
                    printf("\t\toperands[%u].type: imm = 0x%x\n", $i, $op->imm);
                    break;
                case 'mem': // X86_OP_MEM
                    printf("\t\toperands[%u].type: mem\n", $i);
                    if ($op->mem->segment)
                        printf("\t\t\toperands[%u].mem.segment: reg = %s\n", $i, $op->mem->segment);
                    if ($op->mem->base)
                        printf("\t\t\toperands[%u].mem.base: reg = %s\n", $i, $op->mem->base);
                    if ($op->mem->index)
                        printf("\t\t\toperands[%u].mem.index: reg = %s\n", $i, $op->mem->index);
                    if ($op->mem->scale != 1)
                        printf("\t\t\toperands[%u].mem.scale: %u\n", $i, $op->mem->scale);
                    if ($op->mem->disp != 0)
                        printf("\t\t\toperands[%u].mem.disp: 0x%x\n", $i, $op->mem->disp);
                    break;
                default:
                    break;
            }

            // AVX broadcast type
            if ($op->avx_bcast)
                printf("\t\toperands[%u].avx_bcast: %u\n", $i, $op->avx_bcast);

            // AVX zero opmask {z}
            if ($op->avx_zero_opmask)
                printf("\t\toperands[%u].avx_zero_opmask: TRUE\n", $i);

            printf("\t\toperands[%u].size: %u\n", $i, $op->size);

            if ($op->access) {
                printf("\t\toperands[%u].access: %s\n", $i, implode(' | ', $op->access));
            }
        }

    }

    public function getTraceLog()
    {
        return $this->trace_log;
    }

    public function getPeParser()
    {
        return $this->pe_parser;
    }

    public function open()
    {
        if (file_exists($this->fname_pe_parser)) {
            $data = unserialize(file_get_contents($this->fname_pe_parser));
            if ($data instanceof PeParser) {
                $this->pe_parser = $data;
            }
        }

        if (!isset($this->pe_parser)) {
            $this->pe_parser = new PeParser(env('APP_EXE'));
            $this->pe_parser->parsePe();
            file_put_contents($this->fname_pe_parser, serialize($this->pe_parser));
        }

        $this->pe_parser->open();

        if (file_exists($this->fname_trace_log)) {
            $data = unserialize(file_get_contents($this->fname_trace_log));
            if ($data instanceof TraceLog) {
                $this->trace_log = $data;
            }
        }

        if (! isset($this->trace_log)) {
            $info = $this->path_exe.DIRECTORY_SEPARATOR.'bbtrace.'.$this->name_exe.'.log.info';

            $this->trace_log = new TraceLog($info);
            $this->trace_log->parseInfo();
            $this->trace_log->parseFunc();
            $this->trace_log->buildPaging();

            file_put_contents($this->fname_trace_log, serialize($this->trace_log));
        }
    }

    public function save($data)
    {
        if ($data instanceof PeParser) {
            file_put_contents($this->fname_pe_parser, serialize($data));
        } else if ($data instanceof TraceLog) {
            file_put_contents($this->fname_trace_log, serialize($data));
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
                            fprintf(STDERR, "[FuncByIngress-RET] Unknown for %X, with ingress: %X\n", $block_id, $before_id);
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

    public function doAssignJumpAndCallbacks($force)
    {
        $img_base = $this->pe_parser->getHeaderValue('opt.ImageBase');

        $dirty = false;

        foreach($this->trace_log->blocks as $block_id => &$block) {
            if (isset($block['jump']) && isset($block['callbacks'])) {
                if (!$force) continue;
            }

            $ref = $block['module_start_ref'];

            if ($ref != $img_base) continue;

            $start = $block['block_entry'];
            $end = $block['block_end'];
            $rva = $start - $ref;
            $sz = $end - $start;

            $data = $this->pe_parser->getBinaryByRva($rva, $sz);
            $insn = cs_disasm($this->capstone, $data, $start);

            $callbacks = [];

            foreach ($insn as $ins) {
                if (!in_array($ins->mnemonic, ['mov', 'push'])) continue;

                $x86 = &$ins->detail->x86;
                foreach($x86->operands as $op) {
                    if ($op->type === 'imm' && !in_array('write', $op->access)) {

                        $rva = $this->pe_parser->va2rva($op->imm);
                        $s = $this->pe_parser->findSection($rva);

                        if (isset($s)) {
                            $section = $this->pe_parser->getSection($s->n);
                            if (array_key_exists($op->imm, $this->trace_log->blocks)) {
                                $callbacks[ $op->imm ] = self::XREF_TRACE;
                            }
                        }
                    }
                }
            }

            $block['callbacks'] = $callbacks;

            // $ins = end($insn);

            $imm = count($ins->detail->x86->operands) > 0 && $ins->detail->x86->operands[0]->type == 'imm' ? $ins->detail->x86->operands[0]->imm : null;

            $block['jump'] = (object)[
                'address' => $ins->address,
                'mnemonic' => $ins->mnemonic,
                'target' => $imm,
                //'code' => pack('C*', ...$ins->bytes)
            ];

            $this->trace_log->callbacks += $block['callbacks'];
            $dirty = true;

            $stop = $ins->address + count($ins->bytes);
            if ($stop != $end) {
                throw new Exception('Wrong disassmble!');
            }
        }

        return $dirty;
    }

    public function disasm($block_id, $detail)
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

        foreach ($insn as $ins) {
            self::print_ins($ins, $detail);

            if ($detail) {
                $x86 = &$ins->detail->x86;
                self::print_x86_detail($x86);
            }

            printf("\n");
        }

        $stop = $ins->address + count($ins->bytes);
        if ($stop != $end) {
            die('wrong dissamble');
        }

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
        fprintf(STDERR, "callback: %d\n", $this->data->callbacks[$block_id] ?? null);
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

    public function serialize(): string
    {
        return serialize($this->data);
    }

    public function unserialize($serialized)
    {
        $this->data = unserialize($serialized);

        $this->initialize();
    }

    public static function restore()
    {
        $fname = self::makeDumpName();

        if (file_exists($fname)) {
            $bb_analyzer = unserialize(file_get_contents($fname));
            if ($bb_analyzer instanceof BbAnalyzer) {
                return $bb_analyzer;
            }
        }
    }

    public function store()
    {
        file_put_contents($this->fname, serialize($this));
    }

    public function getBlock($id)
    {
        $block = $this->trace_log->blocks[$id] ?? null;

        if (!$block) return;

        $data = [
            'id' => (int)$id,
            'module_id' => $block['module_start_ref'],
            'end' => $block['block_end'],
            'jump' => $block['jump']->mnemonic,
            'ingress' => $this->data->exgress[$id] ?? null,
            'exgress' => $this->data->ingress[$id] ?? null,
        ];
        if (isset($block['function_id'])) {
            $data['function_id'] = $block['function_id'];
        }

        return $data;
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
