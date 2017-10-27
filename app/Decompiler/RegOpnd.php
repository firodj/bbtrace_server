<?php

namespace App\Decompiler;

class RegOpnd extends BaseOperand
{
    public $reg;
    public $size;

    public function __construct($reg, $size) {
        $this->reg = $reg;
        $this->size = $size;
    }

    public function toString($options = [])
    {
        return $this->reg;
    }
}
