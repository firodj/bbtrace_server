<?php

namespace App\Decompiler;

use Exception;

class MovMne extends BaseMnemonic
{
    public function process($state)
    {
        $operands = $this->operands;

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;
        return sprintf("%s = %s", $operands[0], $operands[1]);
    }
}
