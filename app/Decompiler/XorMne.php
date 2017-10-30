<?php

namespace App\Decompiler;

use Exception;

class XorMne extends BaseMnemonic
{
    var $as_mov = null;

    public function process($state)
    {
        $operands = $this->operands;

        if ($operands[0] instanceof RegOpnd && $operands[1] instanceof RegOpnd) {
            if ($operands[0]->reg == $operands[1]->reg) {
                $this->as_mov = $operands[1];
                $k = $operands[0]->reg;
                unset($this->reads[$k]);
                $k = $operands[1]->reg;
                unset($this->reads[$k]);
            }
        }

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;
        $outputs = $this->outputs;

        if ($this->as_mov) {
            return sprintf("%s = 0", $outputs[$operands[0]->reg]);
        }

        return sprintf("%s = %s ^ %s", $outputs[$operands[0]->reg], $operands[0], $operands[1]);
    }
}
