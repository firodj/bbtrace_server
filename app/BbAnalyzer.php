<?php

namespace App;

use Exception;
use Serializable;

class BbAnalyzer implements Serializable
{
    public $fname;

    private $data;

    const XREF_TRACE = 1;

    public function __construct()
    {

        $this->data = (object) [
            'exgress' => [],
            'ingress' => [],
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

    public static function print_ins($ins)
    {
        printf("0x%x:\t%s", $ins->address, $ins->mnemonic);
        if ($ins->op_str) printf("\t\t%s", $ins->op_str);

        return; // FIXME:
        printf("\n");

        printf("bytes:\t%s\n", self::string_hex($ins->bytes));
        printf("\tsize: %s\n", count($ins->bytes));

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

    public function doAssignFunction()
    {
        $this->buildRanges();

        $dirty = false;

        foreach($this->trace_log->blocks as $block_id => &$block) {
            if (isset($block['function_id'])) continue;

            $function_id = NearestValue::array_numeric_sorted_nearest($this->function_ranges,
                $block_id,
                NearestValue::ARRAY_NEAREST_LOWER);

            $function = $this->trace_log->functions[$function_id];

            if ($block_id < $function['function_end']) {
                $dirty = true;
                $block['function_id'] = $function_id;
            } else {
                if (!array_key_exists($block_id, $this->data->ingress)) {
                    continue;
                }
                $befores = array_keys( $this->data->ingress[$block_id] );

                foreach($befores as $before_id) {
                    if (isset($this->trace_log->blocks[$before_id])) {
                        $before = $this->trace_log->blocks[$before_id];

                        if (in_array($before['jump']['mnemonic'], ['jmp', 'jge'])) {
                            if (isset($before['function_id'])) {
                                $dirty = true;
                                $block['function_id'] = $before['function_id'];
                                break;
                            }
                        }
                        if ($before['jump']['mnemonic'] == 'call') {
                            $block['function_id'] = $block_id;
                            if (!array_key_exists($block_id, $this->trace_log->functions)) {
                                $this->trace_log->functions[$block_id] = [
                                    'function_entry' => $block_id,
                                    'function_end' => $block['block_entry'],
                                    'function_name' => 'proc_'.dechex($block_id),
                                ];

                                dump($this->trace_log->functions[$block_id]);
                            }
                            $dirty = true;
                        }
                    } else if (isset($this->trace_log->symbols[$before_id])) {
                        $before = $this->trace_log->symbols[$before_id];
                    }
                }
            }
        }

        return $dirty;
    }

    protected function doAssignJump()
    {
        $img_base = $this->pe_parser->getHeaderValue('opt.ImageBase');

        $dirty = false;

        foreach($this->trace_log->blocks as $block_id => &$block) {
            if (isset($block['jump'])) continue;

            $ref = $block['module_start_ref'];

            if ($ref != $img_base) continue;

            $start = $block['block_entry'];
            $end = $block['block_end'];
            $rva = $start - $ref;
            $sz = $end - $start;

            $data = $this->pe_parser->getBinaryByRva($rva, $sz);
            $insn = cs_disasm($this->capstone, $data, $start);

            $ins = end($insn);

            $block['jump'] = [
                'address' => $ins->address,
                'mnemonic' => $ins->mnemonic,
                'code' => pack('C*', ...$ins->bytes)
            ];
            $dirty = true;

            $stop = $ins->address + count($ins->bytes);
            if ($stop != $end) {
                throw new Exception('Wrong disassmble!');
            }
        }

        return $dirty;
    }

    protected function disasm($block_id)
    {
        $img_base = $this->pe_parser->getHeaderValue('opt.ImageBase');
        //echo sprintf("Rva: 0x%x\n", $block_id, $this->pe_parser->va2rva($block_id));
        if (!isset($this->trace_log->blocks[$block_id])) return;

        $block = $this->trace_log->blocks[$block_id];
        $ref = $block['module_start_ref'];

        if ($ref != $img_base) return;

        $start = $block['block_entry'];
        $end = $block['block_end'];
        $rva = $start - $ref;
        $sz = $end - $start;

        //printf("start: 0x%x, size: %d\n", $rva, $sz);
        $data = $this->pe_parser->getBinaryByRva($rva, $sz);
        //printf("0x%x: %s\n", $rva, self::string_hex($data));

        $insn = cs_disasm($this->capstone, $data, $start);

        //$ins = end($insn);
        //self::print_ins($ins);
        //$x86 = &$ins->detail->x86;
        //self::print_x86_detail($x86);
        //printf("\n");

        foreach ($insn as $ins) {
            self::print_ins($ins);

            //$x86 = &$ins->detail->x86;
            //self::print_x86_detail($x86);

            printf("\n");
        }

        $stop = $ins->address + count($ins->bytes);
        if ($stop != $end) {
            die('wrong dissamble');
        }
    }

    public function doAssignXref()
    {
        if (!empty($this->ingress)) return;

        $last_block_id = null;
        $dirty = false;

        for ($i=1; $i<=$this->trace_log->getLogCount(); $i++) {
            $this->trace_log->parseLog($i, 0,
                function($header, $raw_data) use (&$last_block_id, &$dirty) {
                fprintf(STDERR, "Packet #%d\n", $header['pkt_no']);

                $data = unpack('V*', $raw_data);

                foreach($data as $block_id) {
                    if (!is_null($last_block_id)) {
                        if (!array_key_exists($block_id, $this->data->ingress)) {
                            $this->data->ingress[ $block_id ] = [];
                        }
                        if (!array_key_exists($last_block_id, $this->data->ingress[$block_id])) {
                            $dirty = true;
                            $this->data->ingress[$block_id][$last_block_id] = self::XREF_TRACE;
                        }
                    }
                    $last_block_id = $block_id;
                }

                });
        }

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

    public function doTheBest()
    {
        $dirty = false;
        $dirty |= $this->doAssignJump();
        $dirty |= $this->doAssignFunction();

        if ($dirty) {
            $this->save($this->trace_log);
        }
    }

    public function getBlock($id)
    {
        $block = $this->trace_log->blocks[$id] ?? null;

        if (!$block) return;

        $data = [
            'id' => (int)$id,
            'module_id' => $block['module_start_ref'],
            'end' => $block['block_end'],
            'jump' => $block['jump']['mnemonic'],
            'ingress' => $this->data->exgress[$id] ?? null,
            'exgress' => $this->data->ingress[$id] ?? null,
        ];
        if (isset($block['function_id'])) {
            $data['function_id'] = $block['function_id'];
        }

        return $data;
    }

}
