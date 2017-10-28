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

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        if ($operands[0] instanceof RegOpnd) {
            return sprintf("push(%s)", $operands[0]->reg);
        } else {
            throw new Exception();
        }
    }
}
