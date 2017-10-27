<?php

namespace App\Decompiler;

use Exception;

class MovMne extends BaseMnemonic
{
    public function process()
    {
        $state = $this->state;
        $operands = $this->operands;

        printf("%s = %s\n", $operands[0], $operands[1]);

        return $state;
    }
}