<?php

namespace App\Decompiler;

use Exception;

class NopMne extends BaseMnemonic
{
    public function process($state)
    {
        $operands = $this->operands;

        return $state;
    }

    public function toString($options = [])
    {
        return "// nop";
    }
}
