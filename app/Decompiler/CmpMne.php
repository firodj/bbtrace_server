<?php

namespace App\Decompiler;

use Exception;

class CmpMne extends BaseMnemonic
{
    public function process()
    {
        $state = $this->state;
        $operands = $this->operands;

        printf("cmp(%s, %s)\n", $operands[0], $operands[1]);
        // printf("zf = %s == %s\n", $operands[0], $operands[1]);
        // printf("cf = (unsigned)%s < (unsigned)%s\n", $operands[0], $operands[1]);
        // printf("sf = (signed)((unsigned)%s - (unsigned)%s) < 0\n", $operands[0], $operands[1]);
        // printf("of = (signed)%s < (signed)%s ? !sf : sf\n", $operands[0], $operands[1]);
        // printf("pf = even((unsigned)%s - (unsigned)%s)\n", $operands[0], $operands[1]);
        // printf("// af\n");

        return $state;
    }
}
