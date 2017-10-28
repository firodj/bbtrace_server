<?php

namespace App\Decompiler;

abstract class BaseOperand
{
    public $is_read;
    public $is_write;
    public $size;
    public $rev;

    abstract function toString($options = []);

    public function __construct($size)
    {
        $this->size = $size;
        $this->rev = -1;
    }

    public function __toString()
    {
        return $this->toString();
    }
}
