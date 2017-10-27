<?php

namespace App\Decompiler;

class ImmOpnd extends BaseOperand
{
    public $imm;
    public $size;

    public function __construct($imm, $size) {
        $this->imm = $imm;
        $this->size = $size;
    }

    public function toString($options = [])
    {
        if ($this->size == 4) {
            if (in_array('hex', $options)) {
                return sprintf("0x%x", $this->imm);
            } else {
                return sprintf("%d", $this->imm);
            }
        }

        throw new Exception($this->size);
    }
}
