<?php

namespace App\Decompiler;

use Exception;

class SbbMne extends BaseMnemonic
{
    var $as_self = null;

    public function process($state)
    {
        $operands = $this->operands;

        if ($operands[0] instanceof RegOpnd && $operands[0]->reg == 'esp') {
            throw new Exception();
        }

        if ($operands[0] instanceof RegOpnd && $operands[1] instanceof RegOpnd) {
            if ($operands[0]->reg == $operands[1]->reg) {
                $this->as_self = $operands[0];
            }
        }

        if (!isset($this->reads['cf'])) {
            $this->reads['cf'] = new FlagOpnd('cf');
        }

        return $state;
    }

    public function afterProcess($block, $analyzer, $state)
    {
        $operands = $this->operands;

        if ($operands[0] instanceof RegOpnd && $operands[1] instanceof RegOpnd) {
            if ($operands[1]->rev == null) {
                $operands[1]->rev = $operands[0]->rev;
            }
        }

        return $state;
    }

    public function toString($options = []) {
        $operands = $this->operands;
        $outputs = $this->outputs;

        if ($this->as_self) {
            return sprintf("%s = -%s", $outputs[$operands[0]->reg], $this->reads['cf']);
        }
        return sprintf("%s = %s - (%s + %s)", $outputs[$operands[0]->reg], $operands[0], $operands[1], $this->reads['cf']);
    }
}
