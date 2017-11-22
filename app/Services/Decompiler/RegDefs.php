<?php

namespace App\Services\Decompiler;

use App\Services\Ranger;
use Exception;

class RegDefs
{
    /**
     * @var array<string, RegDef> $reg_defs
     */
    public $reg_defs;

    public function __construct()
    {
        $this->reg_defs = [];
    }

    public function regDef(string $reg)
    {
        if (!array_key_exists($reg, RegDef::X86_REG_DOMAIN)) {
            throw new Exception("Unknown reg def: $reg");
        }

        if (!isset($this->reg_defs[$reg])) {
            $this->reg_defs[$reg] = new RegDef($reg);
        }

        return $this->reg_defs[$reg];
    }

    public function addDefs(array $regs, int $inst_id, State $state)
    {
        $results = [];

        foreach ($regs as $reg) {
            $reg_defuse = $this->regDef($reg)->addDef($inst_id, $state);
            $results[$reg] = $reg_defuse;
        }

        return $results;
    }

    public function regOrders(string $reg, State $state)
    {
        $orders = [$reg => $state->getOrder($reg)];

        $outsides = true;

        foreach (RegDef::regOverlap($reg) as $reg_overlap) {
            $reg_defuse = $this->regDef($reg_overlap)->latestDef($state);

            $orders[$reg_overlap] = $state->getOrder($reg_overlap);

            if ($reg_defuse->rev != 0) $outsides = false;
        }

        if ($outsides) return false;

        arsort($orders);
        return $orders;
    }

    public function addUses(array $regs, int $inst_id, State $state)
    {
        foreach ($regs as $reg) {
            $uses = [];

            $orders = $this->regOrders($reg, $state);

            // if all overlap regs is outsides, pick the same register
            if (! $orders) {
                $uses[] = $reg;
            } else {
                $domain = RegDef::regDomain($reg);
                $r1 = Ranger::fromDomain($domain);
                $result = [$r1];

                foreach(array_keys($orders) as $reg2) {
                    $domain2 = RegDef::regDomain($reg2);
                    $r2 = Ranger::fromDomain($domain2);

                    $result = Ranger::subtracts($result, $r2, $use_reg);

                    if ($use_reg) $uses[] = $reg2;
                }
            }

            foreach ($uses as $_reg) {
                $reg_defuse = $this->regDef($_reg)->latestDef($state);
                $reg_defuse->addUse($inst_id);
            }
        }
    }
}

