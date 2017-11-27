<?php

namespace App\Services;

use App\Services\Decompiler\RegVal;
use PhpAnsiColor\Color;
use Exception;

class ValueAnalyzer extends InstructionAnalyzerBase
{
    public $esp = null;
    public $uses = [];
    public $changes = []; // TODO:

    public function beforeDo()
    {
        $this->esp = clone $this->state->reg_vals['esp'];

        foreach ($this->inst->uses as $defuse) {
            if (! array_key_exists($defuse->reg, $this->state->reg_vals) ) {
                $this->state->reg_vals[$defuse->reg] = RegVal::createOffset($defuse->reg, $defuse->defined_instruction_id);
            }

            if (array_key_exists($defuse->reg, $this->uses) ) {
                fprintf(STDERR, "%s",
                    Color::set(sprintf("Multiple use: #%d %s\n", $this->inst->id, $defuse->reg), 'yellow')
                );
            }
            $this->uses[$defuse->reg] = $this->state->reg_vals[$defuse->reg];
        }
    }

    public function afterDo()
    {
        $defs = $this->inst->defines->pluck('reg');

        foreach ($defs as $reg) {
            if (!array_key_exists($reg, $this->changes)) {
                if ($reg == 'esp') {
                    throw new Exception('ESP cannot be tracked: ' . $this->inst->mne);
                } else {
                    $this->changes[$reg] = RegVal::createOffset($reg, $this->inst->id);
                }
            }
        }

        foreach ($this->changes as $reg => $reg_val) {
            $this->state->reg_vals[$reg] = $reg_val;
        }
    }

    public function doXor()
    {
        $reg = $this->inst->operands[0]->asReg();

        // xor eax, eax -> def only eax
        if ($this->inst->operands[0]->isEqual($this->inst->operands[1])) {
            if ($reg) {
                $this->changes[$reg] = RegVal::createConst(0);
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
                $this->changes[$reg] = RegVal::createConst(-1);
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
                $this->changes[$reg] = RegVal::opAnd( $this->state->reg_vals[$reg], RegVal::createConst($imm) );
            } else {
                throw new Exception();
            }
        }
    }

    public function doPush()
    {
        if ($this->inst->operands[0]->size == 0) throw new Exception();

        $esp = RegVal::opSub( $this->state->reg_vals['esp'], 
            RegVal::createConst( $this->inst->operands[0]->size / 8 ) );
        $this->state->reg_vals['esp'] = $esp;
        $this->changes['esp'] = $esp;

        // put to stack
        $reg = $this->inst->operands[0]->asReg();
        if ($reg) {
            $this->state->stack[ $esp->disp ] = clone $this->state->reg_vals[$reg];
        } else {
            $imm = $this->inst->operands[0]->asImm();
            if (!is_null($imm)) {
                $this->state->stack[ $esp->disp ] = RegVal::createConst($imm);
            }
        }
    }

    public function doPop()
    {
        if ($this->inst->operands[0]->size == 0) throw new Exception();

        // get from stack
        $esp = $this->state->reg_vals['esp'];
        $reg = $this->inst->operands[0]->asReg();
        if ($reg) {
            $this->changes[$reg] = clone $this->state->stack[ $esp->disp ];
        }

        $esp = RegVal::opAdd( $this->state->reg_vals['esp'],
            RegVal::createConst( $this->inst->operands[0]->size / 8 ) );
        $this->state->reg_vals['esp'] = $esp;
        $this->changes['esp'] = $esp;
    }

    public function doRet()
    {
        $esp = RegVal::opAdd( $this->state->reg_vals['esp'],
            RegVal::createConst( $this->state->arch / 8 ) );

        if ($this->opnds(1)) {
            $esp = RegVal::opAdd( $esp,
                RegVal::createConst( $this->inst->operands[0]->asImm() )
            );
        }

        $this->changes['esp'] = $esp;
        $this->state->reg_vals['esp'] = $esp;
    }

    public function doMov()
    {
        $reg = $this->inst->operands[0]->asReg();

        if ($reg) {
            $imm = $this->inst->operands[1]->asImm();
            $regsrc = $this->inst->operands[1]->asReg();
            if (!is_null($imm)) {
                $this->changes[$reg] = RegVal::createConst($imm);
            } else if ($regsrc) {
                $this->changes[$reg] = clone $this->state->reg_vals[$regsrc];
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

    public function doFld()
    {
        $this->uses[] = 'fptop';
        $this->defs[] = 'fptop';

        if ($this->opnds(1)) {
            if ($this->inst->operands[0]->type == OPERAND::REG_TYPE) {
                $fp = $this->fpuReg($this->inst->operands[0]->reg);
                if (is_null($fp)) throw new Exception;
                $this->uses[] = $fp;
            }
        }

        $this->state->fptop_offset = $this->state->fptop_offset > 0 ? $this->state->fptop_offset - 1 : 7;

        // push to st(0)
        $this->defs[] = $this->fpuReg('st0');
    }

    public function doFld1()
    {
        // push +1.0 to st(0)
        $this->doFld();
    }

    public function doFldz()
    {
        // push +0.0 to st(0)
        $this->doFld();
    }

    public function doFild()
    {
        $this->uses[] = 'fptop';
        $this->defs[] = 'fptop';

        $this->state->fptop_offset = $this->state->fptop_offset > 0 ? $this->state->fptop_offset - 1 : 7;

        // push mem to st(0)
        $this->defs[] = $this->fpuReg('st0');
    }

    public function doFst()
    {
        $this->uses[] = 'fptop';

        // get st(0)
        $this->uses[] = $this->fpuReg('st0');

        if ($this->inst->operands[0]->type == OPERAND::REG_TYPE) {
            $fp = $this->fpuReg($this->inst->operands[0]->reg);
            if (is_null($fp)) throw new Exception;

            $this->defs[] = $fp;
        }
    }

    public function doFstp()
    {
        $this->defs[] = 'fptop';

        $this->doFst();

        $this->state->fptop_offset = $this->state->fptop_offset < 7 ? $this->state->fptop_offset + 1 : 0;
    }

    public function doFist()
    {
        $this->uses[] = 'fptop';

        // get st(0)
        $this->uses[] = $this->fpuReg('st0');
    }

    public function doFistp()
    {
        $this->defs[] = 'fptop';

        $this->doFist();

        $this->state->fptop_offset = $this->state->fptop_offset < 7 ? $this->state->fptop_offset + 1 : 0;
    }

    public function fpArith()
    {
        $this->uses[] = 'fptop';
        if ($this->opnds(1)) {
            // st0 <- st0 $ opnd0

            $this->uses[] = $this->fpuReg('st0');
            $this->defs[] = $this->fpuReg('st0');

            if ($this->inst->operands[0]->type == OPERAND::REG_TYPE) {
                $fp = $this->fpuReg($this->inst->operands[0]->reg);
                if (is_null($fp)) throw new Exception;

                $this->uses[] = $fp;
            }
        } else if ($this->opnds(2)) {
            // opnd0 <- opnd0 $ opnd1
            if ($this->inst->operands[0]->type == OPERAND::REG_TYPE) {
                $fp = $this->fpuReg($this->inst->operands[0]->reg);
                if (is_null($fp)) throw new Exception;

                $this->uses[] = $fp;
                $this->defs[] = $fp;
            }

            if ($this->inst->operands[1]->type == OPERAND::REG_TYPE) {
                $fp = $this->fpuReg($this->inst->operands[1]->reg);
                if (is_null($fp)) throw new Exception;

                $this->uses[] = $fp;
            }
        } else {
            throw new Exception();
        }
    }

    public function doFadd()
    {
        $this->fpArith();
    }

    public function doFsub()
    {
        $this->fpArith();
    }

    public function doFdiv()
    {
        $this->fpArith();
    }

    public function doFmul()
    {
        $this->fpArith();
    }

    public function doFaddp()
    {
        if (! $this->opnds(1)) throw new Exception();

        $this->uses[] = 'fptop';
        $this->defs[] = 'fptop';

        $this->uses[] = $this->fpuReg('st0');

        if ($this->inst->operands[0]->type == OPERAND::REG_TYPE) {
            $fp = $this->fpuReg($this->inst->operands[0]->reg);
            if (is_null($fp)) throw new Exception;

            $this->uses[] = $fp;
            $this->defs[] = $fp;
        }

        $this->state->fptop_offset = $this->state->fptop_offset < 7 ? $this->state->fptop_offset + 1 : 0;
    }

    public function doFcom()
    {
        $this->uses[] = 'fptop';
        $this->uses[] = $this->fpuReg('st0');

        if ($this->inst->operands[0]->type == OPERAND::REG_TYPE) {
            $fp = $this->fpuReg($this->inst->operands[0]->reg);
            if (is_null($fp)) throw new Exception;

            $this->uses[] = $fp;
        }
    }

    public function doFxch()
    {
        $this->uses[] = 'fptop';

        $this->uses[] = $this->fpuReg('st0');
        $this->defs[] = $this->fpuReg('st0');

        $fp = $this->fpuReg($this->inst->operands[0]->reg);
        if (is_null($fp)) throw new Exception;

        $this->uses[] = $fp;
        $this->defs[] = $fp;
    }
}
