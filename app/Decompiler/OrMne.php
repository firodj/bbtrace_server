<?php

namespace App\Decompiler;

use Exception;

class OrMne extends BaseMnemonic
{
    var $as_mov = null;

    public function process($state)
    {
        $operands = $this->operands;

        if ($operands[1] instanceof ImmOpnd) {
            if ($operands[1]->imm == 0xffffffff) {
                $this->as_mov = $operands[1];

                if ($operands[0] instanceof RegOpnd) {
                    $k = $operands[0]->reg;
                    unset($this->reads[$k]);
                }
            }
        }

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;
        $outputs = $this->outputs;

        if ($this->as_mov) {
            return sprintf("%s = -1", $outputs[$operands[0]->reg]);
        }

        return sprintf("%s = %s | %s", $outputs[$operands[0]->reg], $operands[0], $operands[1]);
    }
}
