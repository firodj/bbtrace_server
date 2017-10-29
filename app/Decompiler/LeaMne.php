<?php

namespace App\Decompiler;

use Exception;

class LeaMne extends BaseMnemonic
{
    public $is_nop = false;

    public function process($state)
    {
        $operands = $this->operands;

        if (!($operands[1] instanceof MemOpnd)) {
            throw new Exception();
        }

        if ($operands[1]->isOne()) {
            if ($operands[1]->base->reg == $operands[0]->reg) {
                $this->is_nop = true;
                $k = $operands[0]->reg;
                if (isset($this->writes[$k])) {
                    unset($this->writes[$k]);
                }
                $k = $operands[1]->base->reg;
                if (isset($this->reads[$k])) {
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

        if ($this->is_nop) {
            return "// nop";
        }

        return sprintf("%s = %s", $outputs[$operands[0]->reg], $operands[1]->getContent());
    }
}
