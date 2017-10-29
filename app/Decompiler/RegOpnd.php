<?php

namespace App\Decompiler;

class RegOpnd extends BaseOperand
{
    public $reg;

    public function __construct($reg, $size) {
        parent::__construct($size);
        $this->reg = $reg;
    }

    public function toString($options = [])
    {
        if (is_null($this->rev)) {
            return sprintf("%s@?", $this->reg);
        }
        return sprintf("%s@%d", $this->reg, $this->rev);
    }
}
