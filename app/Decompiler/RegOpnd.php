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
        return $this->reg;
    }
}
