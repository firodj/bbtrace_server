<?php

namespace App;

class BbAnalyzer
{
    public function __construct()
    {
        $this->capstone = cs_open(CS_ARCH_X86, CS_MODE_32);
        cs_option($this->capstone, CS_OPT_DETAIL, CS_OPT_ON);
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

    public function open($fname)
    {
        $data = unserialize(file_get_contents($fname));
        if ($data instanceof PeParser) {
            $this->pe_parser = $data;
            $this->pe_parser->open();
            return $data;
        }
        if ($data instanceof TraceLog) {
            $this->trace_log = $data;
            return $data;
        }
    }

    protected function disasm($block_id)
    {
        $img_base = $this->pe_parser->getHeaderValue('opt.ImageBase');
        //echo sprintf("Rva: 0x%x\n", $block_id, $this->pe_parser->va2rva($block_id));
        if (!isset($this->trace_log->blocks[$block_id])) return;

        $block = $this->trace_log->blocks[$block_id];
        $ref = hexdec($block['module_start_ref']);

        if ($ref != $img_base) return;

        $start = hexdec($block['block_entry']);
        $end = hexdec($block['block_end']);
        $rva = $start - $ref;
        $sz = $end - $start;

        //printf("start: 0x%x, size: %d\n", $rva, $sz);

        $data = $this->pe_parser->getBinaryByRva($rva, $sz);
        //printf("0x%x: %s\n", $rva, self::string_hex($data));

        $insn = cs_disasm($this->capstone, $data, $start);

        $ins = end($insn);
        /*
        foreach ($insn as $ins) {
            self::print_ins($ins);

            $x86 = &$ins->detail->x86;
            self::print_x86_detail($x86);

            printf("\n");
        }
        */
        self::print_ins($ins);
        //$x86 = &$ins->detail->x86;
        //self::print_x86_detail($x86);
        printf("\n");

        $stop = $ins->address + count($ins->bytes);
        if ($stop != $end) {
            die('wrong dissamble');
        }
    }

    public function experiment2()
    {
        foreach($this->trace_log->blocks as $block_id=>$block) {
            $this->disasm($block_id);
        }
    }

    public function experiment()
    {
        echo sprintf("Entry: 0x%x\n", $this->pe_parser->getHeaderValue('opt.AddressOfEntryPoint'));

        for ($i=1; $i<=$this->trace_log->getLogCount(); $i++) {
            $this->trace_log->parseLog($i, 0,
                function($header, $raw_data) {
                fprintf(STDERR, "Packet #%d\n", $header['pkt_no']);

                $data = unpack('V*', $raw_data);

                foreach($data as $block_id) {
                    $this->disasm($block_id);

                    if (isset($this->trace_log->functions[$block_id])) {
                        $func = $this->trace_log->functions[$block_id];
                        echo $func['function_name'].PHP_EOL;
                    }
                    if (isset($this->trace_log->blocks[$block_id])) {
                        $block = $this->trace_log->blocks[$block_id];
                        //echo "\t".dechex($block_id).PHP_EOL;
                    } else if (isset($trace_log->symbols[$block_id])) {
                        $sym = $this->trace_log->symbols[$block_id];
                        echo "\t\t".dechex($block_id)." ".$sym['symbol_name'].PHP_EOL;
                    } else {
                        echo sprintf("Unknown: 0x%08x\n", $block_id);
                    }
                }
            });
        }

    }
}
