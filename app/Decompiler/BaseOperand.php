<?php

namespace App\Decompiler;

abstract class BaseOperand
{
    public $is_read;
    public $is_write;

    abstract function toString($options = []);

    public function __toString()
    {
        return $this->toString();
    }
}
