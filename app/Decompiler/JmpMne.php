<?php

namespace App\Decompiler;

use Exception;

class JmpMne extends BaseMnemonic
{
    public function process()
    {
        $state = $this->state;
        $operands = $this->operands;

        if (!($operands[0] instanceof ImmOpnd)) {
            throw new Exception();
        }

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        return sprintf("goto %s", $operands[0]->toString(['hex']));
    }
}
