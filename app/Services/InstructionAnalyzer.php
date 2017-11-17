<?php

namespace App\Services;

use App\Services\Decompiler\State;
use App\Instruction;
use App\Operand;
use App\Expression;

use Exception;

class InstructionAnalyzer
{
    public $inst;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    public function analyze(State $state)
    {
        switch ($this->inst->mne) {
            case 'mov':
                list($defs, $uses) = $this->doMov($state);
                break;
        }

        throw new Exception('Unknown to analyze: ' . $this->inst->mne);

    }

    public function regs(Operand $opnd)
    {
        $regs = [];
        if ($opnd->reg) $reg[] = $opnd->reg;
        if ($opnd->index) $reg[] = $opnd->index;
        return $regs;
    }

    protected function opnds(int $n) {
        return $this->inst->operands->count() == $n;
    }

    protected function doMov(State $state)
    {
        $inst = $this->inst;

        if ($this->opnds(2)) {
            $defs = $this->regs($this->inst->operands[0]);
            $uses = $this->regs($this->inst->operands[1]);

            $state->defs($defs);
            $state->uses($uses);
        }

        dump($state);
    }
}
