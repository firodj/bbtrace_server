<?php

namespace App\Decompiler;

use Exception;

class LeaMne extends BaseMnemonic
{
    public $is_nop = false;

    public function process()
    {
        $state = $this->state;
        $operands = $this->operands;

        if (!($operands[1] instanceof MemOpnd)) {
            throw new Exception();
        }

        if ($operands[1]->isOne()) {
            if ($operands[1]->base->reg == $operands[0]->reg) {
                $this->is_nop = true;
                if (($k = array_search($operands[0]->reg, $this->writes)) !== false){
                    unset($this->writes[$k]);
                }
                if (($k = array_search($operands[1]->base->reg, $this->reads)) !== false){
                    unset($this->reads[$k]);
                }

            }
        }

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        if ($this->is_nop) {
            return "// nop";
        }

        return sprintf("%s = %s", $operands[0], $operands[1]->getContent());
    }
}
