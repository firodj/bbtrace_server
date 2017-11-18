<?php

namespace App\Services\Decompiler;

use App\Services\Ranger;
use App\Operand;
use App\Expression;
use Exception;

class State
{
    public $esp_offset;
    public $st_offset;
    public $reg_defs;
    public $order;

    public function __construct()
    {
        $this->esp_offset = 0;
        $this->st_offset = 0;
        $this->order = 0;
        $this->reg_defs = RegDef::createRegDefs();
    }

    public function defs(array $defs, int $inst_id)
    {
        $this->order += 1;

        foreach ($defs as $reg) {
            $reg_use = $this->reg_defs[$reg]->addDef($inst_id);
            $reg_use->order = $this->order;

            // foreach (RegDef::regOverlap($reg) as $reg_overlap) {
            //     $this->reg_defs[$reg_overlap]->addDef($inst_id, false);
            // }
        }
    }

    public function uses(array $uses, int $inst_id)
    {
        foreach ($uses as $reg) {
            $orders = [];
            $uses = [];

            $reg_defuse = $this->reg_defs[$reg]->latestDef();
            $orders[$reg] = $reg_defuse->order;

            $outsides = true;
            foreach (RegDef::regOverlap($reg) as $reg_overlap) {
                $reg_defuse = $this->reg_defs[$reg_overlap]->latestDef();
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
                $reg_defuse = $this->reg_defs[$_reg]->latestDef();
                $reg_defuse->addUse($inst_id);
            }
        }
    }

}
