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

        printf("goto %s\n", $operands[0]->toString(['hex']));

        return $state;
    }
}
