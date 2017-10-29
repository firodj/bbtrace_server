<?php

namespace App\Decompiler;

use Exception;

class JmpMne extends BaseMnemonic
{
    public $goto = null;

    public function process($state)
    {
        $operands = $this->operands;

        if (!($operands[0] instanceof ImmOpnd)) {
            throw new Exception();
        }

        $this->goto = $operands[0];

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        return sprintf("goto %s", $this->goto->toString(['hex']));
    }
}
