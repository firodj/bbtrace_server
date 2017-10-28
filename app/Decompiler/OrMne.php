<?php

namespace App\Decompiler;

use Exception;

class OrMne extends BaseMnemonic
{
    public function process($state)
    {
        $operands = $this->operands;

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        if ($operands[1] instanceof ImmOpnd) {
            if ($operands[1]->imm == 0xffffffff) {
                if ($operands[0] instanceof RegOpnd) {
                    if (($k = array_search($operands[0]->reg, $this->reads)) !== false){
                        unset($this->reads[$k]);
                    }
                }

                return sprintf("%s = -1", $operands[0], $operands[1]);
            }
        }

        return sprintf("%s = %s | %s", $operands[0], $operands[0], $operands[1]);
    }
}
