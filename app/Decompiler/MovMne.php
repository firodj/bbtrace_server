<?php

namespace App\Decompiler;

use Exception;

class MovMne extends BaseMnemonic
{
    public function process($state)
    {
        $operands = $this->operands;
        $outputs = $this->outputs;

        if ($this->operands[0] instanceof RegOpnd) {
            $output = $outputs[$operands[0]->reg];
            if ($output->size == 1 || $output->size == 2) {
                $temp = clone $output;
                $this->reads[$temp->reg] = $temp;
                $this->operands[2] = $temp;
            } else {
                if ($operands[0]->reg == 'ebp') {
                    if ($operands[1] instanceof RegOpnd && $operands[1]->reg == 'esp') {
                        $state->ebp = $state->esp;
                    } else {
                        $state->ebp = null;
                    }
                }
            }
        }

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;
        $outputs = $this->outputs;

        if ($operands[0] instanceof RegOpnd) {
            $output = $outputs[$operands[0]->reg];

            if (isset($operands[2])) {
                $temp = $operands[2];
                if ($output->size == 1) {
                    if ($output->pos == 1) {
                        return sprintf("%s = (%s & 0xffff00ff) | (%s << 8)", $output, $temp, $operands[1]);
                    } else {
                        return sprintf("%s = (%s & 0xffffff00) | %s", $output, $temp, $operands[1]);
                    }
                } else if ($output->size ==2) {
                    return sprintf("%s = (%s & 0xffff000) | %s", $output, $temp, $operands[1]);
                }
            }
        } else {
            $output = $operands[0];
        }

        return sprintf("%s = %s", $output, $operands[1]);
    }
}
