<?php

namespace App\Services\Decompiler;

class RegDefUse
{
    /**
     * @var string $reg
     */
    public $reg;

    /**
     * List of instructions.id that uses this
     * @var array<int> $uses
     */
    public $uses;

    /**
     * This reviion on the stack
     * @var int $rev
     */
    public $rev;

    /**
     * Whose instructions.id that defines this
     * @var int $inst_id
     */
    public $inst_id;

    /**
     * @var int $order
     */
    public $order;

    public function __construct(string $reg, int $rev, $inst_id = null)
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
