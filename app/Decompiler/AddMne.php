<?php

namespace App\Decompiler;

use Exception;

class AddMne extends BaseMnemonic
{
    public function process($state)
    {
        $operands = $this->operands;
        if ($operands[0] instanceof RegOpnd && $operands[0]->reg == 'esp')
        {
            if ($operands[1] instanceof ImmOpnd) {
                $k = $operands[0]->reg;
                if (($state->reg_changes[$k] ?? State::REV_OUTSIDE) == State::REV_OUTSIDE) {
                    $state->esp -= $operands[1]->imm;

                    unset($this->reads[$k]);
                    unset($this->writes[$k]);
                }
            } else {
                throw new Exception('cannot track ESP');
            }
        }

        return $state;
    }

    public function toString($options = []) {
        $operands = $this->operands;
        $outputs = $this->outputs;

        return sprintf("%s = %s + %s", $outputs[$operands[0]->reg], $operands[0], $operands[1]);
    }
}
