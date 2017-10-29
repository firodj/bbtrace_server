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
        $outputs = $this->outputs;

        return sprintf("%s = %s", $outputs[$operands[0]->reg], $operands[1]);
    }
}
