<?php

namespace App\Services;

use App\Services\Decompiler\State;
use App\Instruction;
use App\Operand;

use Exception;

/**
 * Resource: http://ref.x86asm.net/coder32.html
 */
class DefUseAnalyzer extends InstructionAnalyzerBase
{
    public $uses = [];
    public $defs = [];

    public function beforeDo()
    {
        $ins = app(BbAnalyzer::class)->disasmInstruction($this->inst);

        if (in_array('fpu', $ins->detail->groups)) {
        }

        $this->uses = array_merge($this->uses, array_filter(
            $ins->detail->regs_read,
            function ($reg) { return !in_array($reg, ['eflags', 'flags', 'fpsw']); }
        ));

        $this->defs = array_merge($this->defs, array_filter(
            $ins->detail->regs_write,
            function ($reg) { return !in_array($reg, ['eflags', 'flags', 'fpsw']); }
        ));

        if (in_array('fpu', $ins->detail->groups)) {
            $flags_read = $ins->detail->x86->fpu_flags->test;
        } else {
            $flags_read = $ins->detail->x86->eflags->test;
        }
        $this->uses = array_merge($this->uses, $flags_read);

        if (in_array('fpu', $ins->detail->groups)) {
            $flags_write = array_merge(
                $ins->detail->x86->fpu_flags->modify,
                $ins->detail->x86->fpu_flags->reset,
                $ins->detail->x86->fpu_flags->set,
                $ins->detail->x86->fpu_flags->undefined
            );
        } else {
            $flags_write = array_merge(
                $ins->detail->x86->eflags->modify,
                $ins->detail->x86->eflags->reset,
                $ins->detail->x86->eflags->set,
                $ins->detail->x86->eflags->undefined
            );
        }
        $this->defs = array_merge($this->defs, $flags_write);

        foreach($this->inst->operands as $opnd) {
            if ($opnd->is_read || $opnd->type == OPERAND::MEM_TYPE) {
                $this->uses = array_merge($this->uses, $this->regs($opnd));
            }
            if ($opnd->is_write && $opnd->type == OPERAND::REG_TYPE) {
                $this->defs = array_merge($this->defs, $this->regs($opnd));
            }
        }
    }

    public function afterDo()
    {
        $this->state->uses($this->uses, $this->inst->id);
        $this->state->defs($this->defs, $this->inst->id);
    }

    public function doXor()
    {
        // xor eax, eax -> def only eax
        if ($this->inst->operands[0]->isEqual($this->inst->operands[1])) {
            if ($this->inst->operands[0]->type == OPERAND::REG_TYPE) {
                $this->uses = array_filter($this->uses, function ($reg) {
                    return $reg != $this->inst->operands[0]->reg;
                });
            }
        }
    }

    public function doOr()
    {
        // or eax, -1 -> def only eax
        if ($this->inst->operands[1]->isImm(-1)) {
            if ($this->inst->operands[0]->type == OPERAND::REG_TYPE) {
                $this->uses = array_filter($this->uses, function ($reg) {
                    return $reg != $this->inst->operands[0]->reg;
                });
            }
        }
    }

    public function doPush()
    {
        if ($this->inst->operands[0]->size == 0) throw new Exception();

        $this->state->esp_offset -= $this->inst->operands[0]->size / 8;

        // put to stack
    }

    public function doPop()
    {
        if ($this->inst->operands[0]->size == 0) throw new Exception();

        // get from stack

        $this->state->esp_offset += $this->inst->operands[0]->size / 8;
    }

    public function doFld()
    {
        $this->uses[] = 'fptop';
        $this->defs[] = 'fptop';

        if ($this->inst->operands[0]->type == OPERAND::REG_TYPE) throw new Exception();

        $this->state->fptop_offset = $this->state->fptop_offset > 0 ? $this->state->fptop_offset - 1 : 7;
        $this->defs[] = sprintf("fp%d", $this->state->fptop_offset);

        // push to st(0)
    }

    public function doFstp()
    {
        $this->uses[] = 'fptop';
        $this->defs[] = 'fptop';

        if ($this->inst->operands[0]->type == OPERAND::REG_TYPE) throw new Exception();

        // get st(0) then pop
        $this->uses[] = sprintf("fp%d", $this->state->fptop_offset);

        $this->state->fptop_offset = $this->state->fptop_offset < 7 ? $this->state->fptop_offset + 1 : 0;

    }
}
