<?php

namespace App\Services;

use App\Services\Decompiler\State;
use App\Instruction;
use App\Operand;
use Exception;

abstract class InstructionAnalyzerBase
{
    public $inst;

    public function beforeDo(State $state)
    {
    }

    public function dontKnow(State $state)
    {
    }

    public function afterDo(State $state)
    {
    }

    public function __construct(Instruction $inst)
    {
        $this->inst = $inst;
    }

    public function analyze(State $state)
    {
        $ok = false;

        $this->beforeDo($state);

        $method_name = 'do' . ucfirst($this->inst->mne);

        if (method_exists($this, $method_name)) {
            $ok = $this->$method_name($state);
        } else {
            $ok = $this->dontKnow($state);
        }

        if ($ok === false) {
            throw new Exception('Unknown to analyze: ' . $this->inst->mne);
        }

        $this->afterDo($state);

        return $ok; // TODO: getResult()
    }

    public function regs(Operand $opnd)
    {
        $regs = [];
        if ($opnd->reg) $regs[] = $opnd->reg;
        if ($opnd->index) $regs[] = $opnd->index;
        return $regs;
    }

    public function opnds(int $n)
    {
        return $this->inst->operands->count() == $n;
    }
}
