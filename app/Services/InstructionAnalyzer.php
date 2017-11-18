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
        $ok = false;

        switch ($this->inst->mne) {
            case 'mov':
                $ok = $this->doMov($state);
                break;
            case 'push':
                $ok = $this->doPush($state);
                break;
            case 'pop':
                $ok = $this->doPop($state);
                break;
        }

        if ($ok === true) return;

        throw new Exception('Unknown to analyze: ' . $this->inst->mne);
    }

    public function regs(Operand $opnd)
    {
        $regs = [];
        if ($opnd->reg) $regs[] = $opnd->reg;
        if ($opnd->index) $regs[] = $opnd->index;
        return $regs;
    }

    protected function opnds(int $n) {
        return $this->inst->operands->count() == $n;
    }

    protected function doMov(State $state)
    {
        if ($this->opnds(2)) {
            $defs = $this->regs($this->inst->operands[0]);
            $uses = $this->regs($this->inst->operands[1]);

            $state->uses($uses, $this->inst->id);
            $state->defs($defs, $this->inst->id);

            return true;
        }
    }

    protected function doPush(State $state)
    {
        if ($this->opnds(1)) {
            if ($this->inst->operands[0]->size == 0) throw new Exception();

            $uses = $this->regs($this->inst->operands[0]);
            $uses[] = 'esp';
            $defs = ['esp'];

            $state->uses($uses, $this->inst->id);
            $state->defs($defs, $this->inst->id);

            return true;
        }
    }

    protected function doPop(State $state)
    {
        if ($this->opnds(1)) {
            if ($this->inst->operands[0]->size == 0) throw new Exception();

            $uses = ['esp'];
            $defs = $this->regs($this->inst->operands[0]);
            $defs[] = 'esp';

            $state->uses($uses, $this->inst->id);
            $state->defs($defs, $this->inst->id);

            return true;
        }
    }
}
