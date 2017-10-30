<?php

namespace App\Decompiler;

use Exception;

class TestMne extends BaseMnemonic
{
    var $as_self = null;

    public function process($state)
    {
        $operands = $this->operands;

        if ($operands[0] instanceof RegOpnd && $operands[1] instanceof RegOpnd) {
            if ($operands[0]->reg == $operands[1]->reg) {
                $this->as_self = $operands[0];
            }
        }

        return $state;
    }

    public function afterProcess($block, $analyzer) {
        $operands = $this->operands;

        if ($operands[0] instanceof RegOpnd && $operands[1] instanceof RegOpnd) {
            if ($operands[1]->rev == null) {
                $operands[1]->rev = $operands[0]->rev;
            }
        }
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        return sprintf("test(%s, %s)", $operands[0], $operands[1]);
    }
}
