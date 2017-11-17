<?php

namespace App\Services\Decompiler;

class FrameValue
{
    public $offset;
    public function __construct()
    {
        $this->offset = 0;
    }

    public static function push(int $offset)
    {
        $this->value -= $offset;
    }

    public static function pop(int $offset)
    {
        $this->value += $offset;
    }
}
