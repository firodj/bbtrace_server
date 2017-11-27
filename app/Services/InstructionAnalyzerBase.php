<?php

namespace App\Services;

use App\Services\Decompiler\State;
use App\Services\Decompiler\RegDef;
use App\Instruction;
use App\Operand;
use Exception;

abstract class InstructionAnalyzerBase
{
    public $inst;
    public $state;

    public function beforeDo()
    {
    }

    public function dontKnow()
    {
        return false;
    }

    public function afterDo()
    {
    }

    public function __construct(Instruction $inst, State $state)
    {
        $this->inst = $inst;
        $this->state = $state;
    }

    public function analyze()
    {
        $ok = false;

        $this->beforeDo();

        $method_name = 'do' . ucfirst($this->inst->mne);

        if (method_exists($this, $method_name)) {
            $ok = $this->$method_name();
        } else {
            $ok = $this->dontKnow();
        }

        if ($ok === false) {
            throw new Exception('Unknown to analyze: ' . $this->inst->mne);
        }

        $this->afterDo();
        return $ok;
    }

    public function regs(Operand $opnd)
    {
        $regs = [];
        if ($opnd->reg && RegDef::regDomain($opnd->reg)) $regs[] = $opnd->reg;
        if ($opnd->index) $regs[] = $opnd->index;
        return $regs;
    }

    public function opnds(int $n)
    {
        return $this->inst->operands->count() == $n;
    }

    public function fpuReg($reg)
    {
        if (preg_match('/^st([0-9]+)$/', $reg, $matches)) {
            $i = (int) $matches[1];
            return sprintf("fp%d", ($this->state->fptop_offset + $i) % 8);
        }
    }
}
