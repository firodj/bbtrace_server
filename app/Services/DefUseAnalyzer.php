<?php

namespace App\Services;

use App\Services\Decompiler\State;
use App\Instruction;
use App\Operand;
use PhpAnsiColor\Color;

use Exception;

/**
 * Resource: http://ref.x86asm.net/coder32.html
 */
class DefUseAnalyzer extends InstructionAnalyzerBase
{
    public $uses = [];
    public $defs = [];

    public function dontKnow()
    {
        return null;
    }

    public function beforeDo()
    {
        $ins = app(BbAnalyzer::class)->disasmInstruction($this->inst);

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
        $this->uses = array_unique($this->uses);
        $this->defs = array_unique($this->defs);

        $this->state->uses($this->uses, $this->inst->id);
        $this->state->defs($this->defs, $this->inst->id);
    }

    public function doFstsw()
    {
        $this->uses[] = 'fpsw';
    }

    public function doFnstsw()
    {
        $this->doFstsw();
    }

    public function doFstcw()
    {
        $this->uses[] = 'fpcw';
    }

    public function doFnstcw()
    {
        $this->doFstcw();
    }

    public function doFldcw()
    {
        $this->defs[] = 'fpcw';
    }
}
