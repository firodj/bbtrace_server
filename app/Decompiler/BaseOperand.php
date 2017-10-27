<?php

namespace App\Decompiler;

abstract class BaseOperand
{
    abstract function toString($options = []);

    public function __toString()
    {
        return $this->toString();
    }
}
