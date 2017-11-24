<?php

namespace App\Services;

use App\Services\Decompiler\RegVal;
use Exception;

class ValueAnalyzer extends InstructionAnalyzerBase
{
    public $esp = null;
    public $uses = [];

    public function beforeDo()
    {
        $this->esp = clone $this->state->reg_vals['esp'];

        foreach ($this->inst->uses as $defuse) {
            if (! array_key_exists($defuse->reg, $this->state->reg_vals) ) {
                $this->state->reg_vals[$defuse->reg] = RegVal::createOffset($defuse->reg, $defuse->defined_instruction_id);
            }
        }
    }

    public function afterDo()
    {
        $defs = $this->inst->defines->pluck('reg');

        if ($defs->contains('esp')) {
            if ($this->esp->isEqual( $this->state->reg_vals['esp'] )) {
                throw new Exception('ESP cannot be tracked: ' . $this->inst->mne);
            }
        }

        $this->uses = [];
        foreach ($this->inst->uses as $defuse) {
            $this->uses[$defuse->reg] = $this->state->reg_vals[$defuse->reg];
        }
    }

    public function doXor()
    {
        $reg = $this->inst->operands[0]->asReg();

        // xor eax, eax -> def only eax
        if ($this->inst->operands[0]->isEqual($this->inst->operands[1])) {
            if ($reg) {
                $this->state->reg_vals[$reg] = RegVal::createConst(0);
            }
        } else {
            throw new Exception();
        }
    }

    public function doOr()
    {
        $reg = $this->inst->operands[0]->asReg();

        // or eax, -1 -> def only eax
        if ($this->inst->operands[1]->isImm(-1)) {
            if ($reg) {
                $this->state->reg_vals[$reg] = RegVal::createConst(0);
            }
        } else {
            throw new Exception();
        }
    }

    public function doAnd()
    {
        $reg = $this->inst->operands[0]->asReg();

        $imm = $this->inst->operands[1]->asImm(true);

        if ($reg) {
            if (!is_null($imm)) {
                if ($this->inst->operands[1]->isImm(-1)) {
                    $this->state->reg_vals[$reg] = RegVal::createConst(-1);
                } else {
                    $this->state->reg_vals[$reg]->opAnd( RegVal::createConst($imm) );
                }
            } else {
                throw new Exception();
            }
        }
    }

    public function doPush()
    {
        if ($this->inst->operands[0]->size == 0) throw new Exception();

        $this->state->reg_vals['esp']->opSub( RegVal::createConst( $this->inst->operands[0]->size / 8 ) );

        // put to stack
        $reg = $this->inst->operands[0]->asReg();
        if ($reg) {
            $this->state->stack[ $this->state->reg_vals['esp']->disp ] = clone $this->state->reg_vals[$reg];
        } else {
            $imm = $this->inst->operands[0]->asImm();
            if (!is_null($imm)) {
                $this->state->stack[ $this->state->reg_vals['esp']->disp ] = RegVal::createConst($imm);
            }
        }
    }

    public function doPop()
    {
        if ($this->inst->operands[0]->size == 0) throw new Exception();

        // get from stack
        $reg = $this->inst->operands[0]->asReg();
        if ($reg) {
            $this->state->reg_vals[$reg] = clone $this->state->stack[ $this->state->reg_vals['esp']->disp ];
        }

        $this->state->reg_vals['esp']->opAdd( RegVal::createConst( $this->inst->operands[0]->size / 8 ) );
    }

    public function doRet()
    {
        $this->state->reg_vals['esp']->opAdd( RegVal::createConst( $this->state->arch / 8 ) );

        if ($this->opnds(1)) {
            $this->state->reg_vals['esp']->opAdd( RegVal::createConst( $this->inst->operands[0]->asImm() ) );
        }
    }

    public function doMov()
    {
        $reg = $this->inst->operands[0]->asReg();

        if ($reg) {
            $imm = $this->inst->operands[1]->asImm();
            $regsrc = $this->inst->operands[1]->asReg();
            if (!is_null($imm)) {
                $this->state->reg_vals[$reg] = RegVal::createConst($imm);
            } else if ($regsrc) {
                $this->state->reg_vals[$reg] = clone $this->state->reg_vals[$regsrc];
            } else {
                $this->state->reg_vals[$reg] = RegVal::createUnknown();
                // throw new Exception();
            }
        }
    }

    public function doCmp()
    {
    }

    public function doTest()
    {
    }

    public function doJe()
    {
    }

    public function doJne()
    {
    }

    public function doNop()
    {
    }
}
