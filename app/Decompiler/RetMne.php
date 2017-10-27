<?php

namespace App\Decompiler;

use Exception;

class RetMne extends BaseMnemonic
{
    public function process()
    {
        $state = $this->state;
        $operands = $this->operands;

        printf("return");
        if ($operands[0] instanceof ImmOpnd) {
            $state->esp += $operands[0]->imm;
            printf(" then pop(%s)\n", $operands[0]);
        } else {
            throw new Exception();
        }

        printf("\n");

        return $state;
    }
}
