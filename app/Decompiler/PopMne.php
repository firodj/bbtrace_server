<?php

namespace App\Decompiler;

class PopMne extends BaseMnemonic
{
    public function process($state)
    {
        $operands = $this->operands;
        $outputs = $this->outputs;

        // pop, then change esp
        $opnd = $state->popStack();

        $output = $outputs[$operands[0]->reg];

        if ($opnd->reg == $output->reg) {
            $output->rev = $opnd->rev;
            $state->reg_changes[$output->reg] = $output->rev;
        }

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;
        $outputs = $this->outputs;

        if ($operands[0] instanceof RegOpnd) {
            return sprintf("%s = pop()", $outputs[$operands[0]->reg]);
        } else {
            throw new Exception();
        }
    }
}
