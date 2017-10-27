<?php

namespace App\Decompiler;

class PopMne extends BaseMnemonic
{
    public function process()
    {
        $state = $this->state;
        $operands = $this->operands;

        // pop, then change esp
        $state->esp += 4;

        if ($operands[0] instanceof RegOpnd) {
            printf("%s = pop()\n", $operands[0]->reg);
        } else {
            throw new Exception();
        }

        return $state;
    }
}
