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

    public function beforeDo(State $state)
    {
        $ins = app(BbAnalyzer::class)->disasmInstruction($this->inst);

        if ($this->inst->mne[0] == 'f') {
            dump($ins);
        }

        $this->uses = array_merge($this->uses, array_filter(
            $ins->detail->regs_read,
            function ($reg) { return !in_array($reg, ['eflags', 'flags']); }
        ));

        $this->defs = array_merge($this->defs, array_filter(
            $ins->detail->regs_write,
            function ($reg) { return !in_array($reg, ['eflags', 'flags']); }
        ));

        $flags_read = $ins->detail->x86->eflags->test;
        $this->uses = array_merge($this->uses, $flags_read);

        $flags_write = array_merge(
            $ins->detail->x86->eflags->modify,
            $ins->detail->x86->eflags->reset,
            $ins->detail->x86->eflags->set,
            $ins->detail->x86->eflags->undefined
        );
        $this->defs = array_merge($this->defs, $flags_write);

        foreach($this->inst->operands as $opnd) {
            if ($opnd->is_read) {
                $this->uses = array_merge($this->uses, $this->regs($opnd));
            }
            if ($opnd->is_write && $opnd->type == OPERAND::REG_TYPE) {
                $this->defs = array_merge($this->defs, $this->regs($opnd));
            }
        }
    }

    public function afterDo(State $state)
    {
        $state->uses($this->uses, $this->inst->id);
        $state->defs($this->defs, $this->inst->id);

        if ($this->inst->mne[0] == 'f') {
            fprintf(STDERR, "%d: %s", $this->inst->addr, $this->inst->toString());
            fprintf(STDERR, "\tuses: %s", implode(', ', $this->uses));
            fprintf(STDERR, "\tdefs: %s\n", implode(', ', $this->defs));
        }
    }

    public function doXor(State $state)
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

    public function doOr(State $state)
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
}
