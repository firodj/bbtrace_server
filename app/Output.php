<?php

namespace App;

class Output
{
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

}
