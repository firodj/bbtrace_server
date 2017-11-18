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
     * @var int $st_offset
     */
    public $st_offset;

    /**
     * @var array<string, int> $reg_revs
     */
    public $reg_revs;

    /**
     * @var RegDefs $reg_defs
     */
    public $reg_defs;

    public function __construct(RegDefs $reg_defs)
    {
        $this->esp_offset = 0;
        $this->st_offset = 0;
        $this->reg_revs = [];
        $this->reg_defs = $reg_defs;
    }

    public static function createState()
    {
        $reg_defs = new RegDefs;
        return new State($reg_defs);
    }

    public function defs(array $defs, int $inst_id)
    {
        return $this->reg_defs->addDefs($defs, $inst_id, $this);
    }

    public function getRev(string $reg)
    {
        if (!array_key_exists($reg, RegDef::X86_REG_DOMAIN)) {
            throw new Exception("Unknown reg def: $reg");
        }

        if (! isset($this->reg_revs[$reg])) return 0;

        return $this->reg_revs[$reg];
    }

    public function setRev(string $reg, int $rev)
    {
        if (!array_key_exists($reg, RegDef::X86_REG_DOMAIN)) {
            throw new Exception("Unknown reg def: $reg");
        }

        $this->reg_revs[$reg] = $rev;

        return $rev;
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
