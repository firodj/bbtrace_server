<?php

namespace App\Decompiler;

use Exception;

class SarMne extends BaseMnemonic
{
    public function process()
    {
        $state = $this->state;
        $operands = $this->operands;

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        if ($operands[1] instanceof ImmOpnd) {
            return sprintf("%s = %s >> %s", $operands[0], $operands[0], $operands[1]);
        } else {
            throw new Exception;
        }
    }
}
