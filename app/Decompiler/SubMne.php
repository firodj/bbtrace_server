<?php

namespace App\Decompiler;

use Exception;

class SubMne extends BaseMnemonic
{
    public function process($state)
    {
        $operands = $this->operands;

        // printf("zf = %s == %s\n", $operands[0], $operands[1]);
        // printf("cf = (unsigned)%s < (unsigned)%s\n", $operands[0], $operands[1]);
        // printf("sf = (signed)((unsigned)%s - (unsigned)%s) < 0\n", $operands[0], $operands[1]);
        // printf("of = (signed)%s < (signed)%s ? !sf : sf\n", $operands[0], $operands[1]);
        // printf("pf = even((unsigned)%s - (unsigned)%s)\n", $operands[0], $operands[1]);
        // printf("//af\n");

        return $state;
    }

    public function toString($options = []) {
        $operands = $this->operands;

        return sprintf("%s = %s - %s", $operands[0], $operands[0], $operands[1]);
    }
}
