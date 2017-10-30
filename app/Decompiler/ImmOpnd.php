<?php

namespace App\Decompiler;

use Exception;
    
class ImmOpnd extends BaseOperand
{
    public $imm;

    public function __construct($imm, $size) {
        parent::__construct($size);
        $this->imm = $imm;
    }

    public function toString($options = [])
    {
        if (isset($this->display_name)) return $this->display_name;

        if (in_array('hex', $options)) {
            return sprintf("0x%x", $this->imm);
        } else {
            return sprintf("%d", $this->imm);
        }
    }
}
