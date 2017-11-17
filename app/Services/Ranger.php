<?php

namespace App\Services;

class Ranger
{
    public $start;
    public $end;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    public static function isOverlap(Ranger $r1, Ranger $r2)
    {
        return ($r1->end > $r2->start && $r2->end > $r1->start);
    }

    public static function isTouch(Ranger $r1, Ranger $r2)
    {
        return ($r1->end >= $r2->start && $r2->end >= $r1->start);
    }

    public static function subtract(Ranger $r1, Ranger $r2)
    {
        if (!self::isOverlap($r1, $r2)) return [$r1];

        $result = [];

        if ($r1->start < $r2->start) {
            $result[] = new Ranger($r1->start, $r2->start);
        }
        if ($r2->end < $r1->end) {
            $result[] = new Ranger($r2->end, $r1->end);
        }

        return $result;
    }

    public static function fromDomain(array $domain)
    {
        return new Ranger($domain[1], $domain[1] + $domain[2]);
    }

    public static function merge(array $rs)
    {
        $result = [];

        foreach ($rs as $r) {
            if (empty($result)) {
                $result[] = $r;
            } else {
                $glues = [];
                $rg = clone $r;
                $_result = [];
                foreach ($result as $_r) {
                    if (self::isTouch($r, $_r)) {
                        $glues[] = $_r;
                        if ($rg->start > $_r->start) {
                            $rg->start = $_r->start;
                        }
                        if ($rg->end < $_r->end) {
                            $rg->end = $_r->end;
                        }
                    } else {
                        $_result[] = $_r;
                    }
                }

                if (empty($glues)) {
                    $_result[] = $r;
                } else {
                    $_result[] = $rg;
                }

                $result = $_result;
            }
        }

        usort($result, function ($a, $b) {
            if ($a->start < $b->start) return -1;
            else if ($a->start > $b->start) return +1;
            return 0;
        });

        return $result;
    }
}
