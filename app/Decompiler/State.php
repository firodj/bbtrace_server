<?php

namespace App\Decompiler;

class State
{
    public $esp;

    public function __construct()
    {
        $this->esp = 0;
    }

}
