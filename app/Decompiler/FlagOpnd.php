<?php

namespace App\Decompiler;

class FlagOpnd extends BaseOperand
{
    public $flag;

    public function __construct($flag) {
        parent::__construct(0);
        $this->flag = $flag;
    }

    public function toString($options = [])
    {
        if (is_null($this->rev)) {
            return sprintf("%s@?", $this->flag);
        }
        return sprintf("%s@%d", $this->flag, $this->rev);
    }
}
