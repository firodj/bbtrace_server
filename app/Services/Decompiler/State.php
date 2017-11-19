<?php

namespace App\Services\Decompiler;

use App\Services\Ranger;
use App\Operand;
use App\Expression;
use Exception;

class State
{
    /**
     * @var int $esp_offset
     */
    public $esp_offset;

    /**
     * FPU top pointer
     * @var int $fptop_offset
     */
    public $fptop_offset;

    /**
     * @var array<string, int> $reg_revs
     */
    public $reg_revs;

    /**
     * @var array<string, int> $reg_orders
     */
    public $reg_orders;

    /**
     * @var RegDefs $reg_defs
     */
    public $reg_defs;

    /**
     * @var int $def_order
     */
    public $def_order;

    /**
     * @var array<int, RegDef> $stack
     */
    public $stack;

    /**
     * Architecture bit
     * @var int $arch
     */
    public $arch;

    public function __construct(RegDefs $reg_defs)
    {
        $this->esp_offset = 0;
        $this->fptop_offset = 0;
        $this->def_order = 0;
        $this->reg_revs = [];
        $this->reg_orders = [];
        $this->stack = [];
        $this->reg_defs = $reg_defs;

        $this->arch = 32; // 32-bit
    }

    public static function createState()
    {
        $reg_defs = new RegDefs;
        return new State($reg_defs);
    }

    public function defs(array $defs, int $inst_id)
    {
        $order = ++$this->def_order;

        $results = $this->reg_defs->addDefs($defs, $inst_id, $this);
        foreach ($results as $reg => $reg_defuse) {
            $this->setOrder($reg, $order);
        }
    }

    public function getRev(string $reg)
    {
        if (! isset($this->reg_revs[$reg])) return 0;

        return $this->reg_revs[$reg];
    }

    public function setRev(string $reg, int $rev)
    {
        $this->reg_revs[$reg] = $rev;

        return $rev;
    }

    public function getOrder(string $reg)
    {
        if (! isset($this->reg_orders[$reg])) return 0;

        return $this->reg_orders[$reg];
    }

    public function setOrder(string $reg, int $order)
    {
        $this->reg_orders[$reg] = $order;

        return $order;
    }

    public function uses(array $uses, int $inst_id)
    {
        return $this->reg_defs->addUses($uses, $inst_id, $this);
    }

    public function latestDef(string $reg)
    {
        return $this->reg_defs->regDef($reg)->latestDef($this);
    }
}
