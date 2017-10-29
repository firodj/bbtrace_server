<?php

namespace App\Decompiler;

use Exception;

class SubMne extends BaseMnemonic
{
    public function process($state)
    {
        $operands = $this->operands;

        // printf("zf = %s == %s\n", $operands[0], $operands[1]);
        // printf("cf = (unsigned)%s < (unsigned)%s\n", $operands[0], $operands[1]);
        // printf("sf = (signed)((unsigned)%s - (unsigned)%s) < 0\n", $operands[0], $operands[1]);
        // printf("of = (signed)%s < (signed)%s ? !sf : sf\n", $operands[0], $operands[1]);
        // printf("pf = even((unsigned)%s - (unsigned)%s)\n", $operands[0], $operands[1]);
        // printf("//af\n");

        if ($operands[0] instanceof RegOpnd && $operands[0]->reg == 'esp')
        {
            if ($operands[1] instanceof ImmOpnd)
            {
                $k = $operands[0]->reg;
                if (($state->reg_changes[$k] ?? -1) == -1) {
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

        return sprintf("%s = %s - %s", $outputs[$operands[0]->reg], $operands[0], $operands[1]);
    }
}
