<?php

namespace App\Decompiler;

use Exception;

class OrMne extends BaseMnemonic
{
    public function process()
    {
        $state = $this->state;
        $operands = $this->operands;

        if ($operands[1] instanceof ImmOpnd) {
            if ($operands[1]->imm == 0xffffffff) {
                printf("%s = -1\n", $operands[0], $operands[1]);
                return $state;
            }
        }

        printf("%s |= %s\n", $operands[0], $operands[1]);

        return $state;
    }
}
