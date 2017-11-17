<?php

namespace App\Services\Decompiler;

class RegDefUse
{
    public $reg;
    public $uses;
    public $rev;
    public $inst_id;
    public $order;

    public function __construct($reg, $rev, $inst_id = null)
    {
        $this->reg = $reg;
        $this->rev = $rev;
        $this->inst_id = $inst_id;
        $this->order = null;
        $this->uses = [];
    }

    public function addUse(int $inst_id)
    {
        if (! in_array($inst_id, $this->uses)) {
            $this->uses[] = $inst_id;
        }
    }
}
