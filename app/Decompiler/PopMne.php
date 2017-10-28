<?php

namespace App\Decompiler;

class PopMne extends BaseMnemonic
{
    public function process($state)
    {
        $operands = $this->operands;

        // pop, then change esp
        $opnd = $state->popStack();

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        if ($operands[0] instanceof RegOpnd) {
            return sprintf("%s = pop()", $operands[0]->reg);
        } else {
            throw new Exception();
        }
    }
}
