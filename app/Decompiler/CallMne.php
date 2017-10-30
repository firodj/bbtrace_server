<?php

namespace App\Decompiler;

use Exception;
use App\Subroutine;

class CallMne extends BaseMnemonic
{
    public $goto = null;
    public $subroutine = null;

    public function process($state)
    {
        $operands = $this->operands;

        if (!($operands[0] instanceof ImmOpnd)) {
            throw new Exception();
        }

        if ($operands[0] instanceof ImmOpnd) {
            $this->goto = $operands[0];
            $this->subroutine = Subroutine::find($this->goto);
            if ($this->subroutine) {
                if (is_null($this->subroutine->esp)) {
                    throw new Exception("Subroutine not analyzed");
                }
                $state->esp += $this->subroutine->esp;
            } else {
                throw new Exception("Call to undefined subroutine");
            }
        }

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        if ($this->subroutine) {
            return sprintf("call %s with pop(%d)", $this->subroutine->name, $this->subroutine->esp);
        }
        return sprintf("call %s", $this->goto->toString(['hex']));
    }

}
