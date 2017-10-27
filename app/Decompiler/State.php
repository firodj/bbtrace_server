<?php

namespace App\Decompiler;

class State
{
    public $esp;
    public $arg;

    public function __construct()
    {
        $this->esp = 0;
        $this->arg = 0;
    }

}
