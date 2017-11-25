<?php

namespace App\Services\Decompiler;

use App\Block;

class BlockWrap
{
    public $block;

    public $in_states;

    public function __construct(Block $block)
    {
        $this->block = $block;
        $this->in_states = [];
    }
}
