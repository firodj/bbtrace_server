<?php

namespace App\Services\Decompiler;

/*
 * esp = frame + 0;
 */
class Value
{
    public $value;
    public $reg;
    public $type;

    const FRAME_TYPE = 'frame';
    const EXACT_TYPE = 'exact';
    const ABSTRACT_TYPE = 'abstract';

    public function __construct()
    {
        $this->value = null;
        $this->reg = null;
        $this->type = self::ABSTRACT_TYPE;
    }

    public static function createFrame(int $offset)
    {
        $value = new self;
        $value->type = self::FRAME_TYPE;
        $value->value = $offset;

        return $value;
    }

    public static function createExact(int $exact)
    {
        $value = new self;
        $value->type = self::EXACT_TYPE;
        $value->value = $exact;

        return $value;
    }

    
}
