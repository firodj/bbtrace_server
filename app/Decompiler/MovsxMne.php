<?php

namespace App\Decompiler;

use Exception;

class MovsxMne extends BaseMnemonic
{
    public function process($state)
    {
        $operands = $this->operands;
        $outputs = $this->outputs;
        $output = $outputs[$operands[0]->reg];

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;
        $outputs = $this->outputs;

        $output = $outputs[$operands[0]->reg];

        if ($operands[1]->size == 1) {
            if ($output->pos == 1) {
                return sprintf("%s = (signed) (%s >> 8) & 0xff", $output, $operands[1]);
            } else {
                return sprintf("%s = (signed) %s & 0xff", $output, $operands[1]);
            }
        } else if ($operands[1]->size == 2) {
            return sprintf("%s = (signed) %s & 0xffff", $output, $operands[1]);
        } else {
            throw new Exception();
        }
    }
}
