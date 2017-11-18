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

    /**
     * @var int $def_order
     */
    public $def_order;

    public function __construct()
    {
        $this->reg_defs = [];
        $this->def_order = 0;
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
        $order = ++$this->def_order;

        foreach ($regs as $reg) {
            $reg_defuse = $this->regDef($reg)->addDef($inst_id, $state);
            if (is_null($reg_defuse->order)) {
                $reg_defuse->order = $order;
            }
        }
    }

    public function addUses(array $regs, int $inst_id, State $state)
    {
        foreach ($regs as $reg) {
            $orders = [];
            $uses = [];

            $reg_defuse = $this->regDef($reg)->latestDef($state);
            $orders[$reg] = $reg_defuse->order;

            $outsides = true;
            foreach (RegDef::regOverlap($reg) as $reg_overlap) {
                $reg_defuse = $this->regDef($reg_overlap)->latestDef($state);
                $orders[$reg_overlap] = $reg_defuse->order;
                if ($reg_defuse->rev != 0) $outsides = false;
            }

            // if all overlap regs is outsides, pick the same register
            if ($outsides) {
                $uses[] = $reg;
            } else {

                arsort($orders);

                $domain = RegDef::regDomain($reg);
                $r1 = Ranger::fromDomain($domain);
                $result = [$r1];

                // fprintf(STDERR, "use? %s\n", $reg);
                foreach(array_keys($orders) as $_reg) {
                    // fprintf(STDERR, "+ check? %s\n", $_reg);

                    $_domain = RegDef::regDomain($_reg);
                    $r2 = Ranger::fromDomain($_domain);

                    $_result = [];
                    foreach($result as $_r) {
                        if (Ranger::isOverlap($_r, $r2)) {
                            $uses[] = $_reg;
                            //fprintf(STDERR, "++ use: %s\n", $_reg);
                        }
                        //fprintf(STDERR, "   [%d..%d] - [%d..%d]\n", $_r->start, $_r->end, $r2->start, $r2->end);
                        $_result = array_merge($_result, Ranger::subtract($_r, $r2));
                    }
                    $result = Ranger::merge($_result);
                }
            }

            foreach ($uses as $_reg) {
                $reg_defuse = $this->regDef($_reg)->latestDef($state);
                $reg_defuse->addUse($inst_id);
            }
        }
    }
}

