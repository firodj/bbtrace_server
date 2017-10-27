<?php

namespace App\Decompiler;

class PushMne extends BaseMnemonic
{
    public function process()
    {
        $state = $this->state;
        $operands = $this->operands;

        // change esp first, then push
        $state->esp -= 4;

        if ($operands[0] instanceof RegOpnd) {
            printf("push(%s)\n", $operands[0]->reg);
        } else {
            throw new Exception();
        }

        return $state;
    }
}
