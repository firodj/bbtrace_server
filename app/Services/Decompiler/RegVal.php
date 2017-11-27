<?php

namespace App\Services\Decompiler;

class RegVal
{
    public $disp;

    public $type;

    public $reg;

    public $def_inst_id;

    const UNKNOWN_TYPE = -1;
    const OFFSET_TYPE = 0;
    const CONST_TYPE = 1;

    public function __construct()
    {
        $this->disp = 0;
    }

    public static function createOffset($reg, $def_inst_id, $disp = 0)
    {
        $that = new RegVal;
        $that->type = self::OFFSET_TYPE;
        $that->reg = $reg;
        $that->def_inst_id = $def_inst_id;
        $that->disp = $disp;

        return $that;
    }

    public static function createConst($disp)
    {
        $that = new RegVal;
        $that->type = self::CONST_TYPE;
        $that->disp = $disp;

        return $that;
    }

    public static function createUnknown()
    {
        $that = new RegVal;
        $that->type = self::UNKNOWN_TYPE;

        return $that;
    }

    public function isEqual(RegVal $that)
    {
        if ($this->type != $that->type) return false;
        if ($this->disp != $that->disp) return false;
        if ($this->reg != $that->reg) return false;
        if ($this->def_inst_id != $that->def_inst_id) return false;
        return true;
    }

    public static function opAnd(RegVal $dest, RegVal $src): RegVal
    {
        $dest = clone $dest;
        if ($src->type == self::CONST_TYPE) {
            $dest->disp &= $src->disp;
            return $dest;
        }

        throw new Exception();
    }

    public static function opSub(RegVal $dest, RegVal $src): RegVal
    {
        $dest = clone $dest;
        if ($src->type == self::CONST_TYPE) {
            $dest->disp -= $src->disp;
            return $dest;
        }

        throw new Exception();
    }

    public static function opAdd(RegVal $dest, RegVal $src): RegVal
    {
        if ($src->type == self::CONST_TYPE) {
            $dest->disp += $src->disp;
            return $dest;
        }

        throw new Exception();
    }

}
