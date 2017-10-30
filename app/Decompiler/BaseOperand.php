<?php

namespace App\Decompiler;

abstract class BaseOperand
{
    public $is_read;
    public $is_write;
    public $size;
    public $rev;
    public $display_name = null;

    abstract function toString($options = []);

    public function __construct($size)
    {
        $this->size = $size;
        $this->rev = null;
    }

    public function __toString()
    {
        return $this->toString();
    }
}
