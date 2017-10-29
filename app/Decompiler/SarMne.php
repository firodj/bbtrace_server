<?php

namespace App\Decompiler;

use Exception;

class SarMne extends BaseMnemonic
{
    public function process($state)
    {
        $operands = $this->operands;

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;
        $outputs = $this->outputs;

        if ($operands[1] instanceof ImmOpnd) {
            return sprintf("%s = %s >> %s", $outputs[$operands[0]->reg], $operands[0], $operands[1]);
        } else {
            throw new Exception;
        }
    }
}
