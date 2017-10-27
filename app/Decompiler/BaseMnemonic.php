<?php

namespace App\Decompiler;

use Exception;

abstract class BaseMnemonic
{
    public $ins;
    public $state;
    public $operands;
    public $detail;

    public function __construct($ins, $state)
    {
        $this->ins = $ins;
        $this->state = $state;
        $this->detail = $ins->detail->x86;
        $this->createOperands();
        $this->detectArg();
    }

    abstract public function process();

    protected function detectArg()
    {
        foreach ($this->operands as $opnd) {
            if ($opnd instanceof MemOpnd && $opnd->isArg()) {
                $this->state->arg = $opnd->var;
            }
        }
    }

    protected function createOperands()
    {
        $operands = $this->ins->detail->x86->operands;
        $state = $this->state;
        $this->operands = [];

        foreach($operands as $opnd) {
            switch ($opnd->type) {
            case 'reg':
                $this->operands[] = new RegOpnd($opnd->reg, $opnd->size);
                break;
            case 'mem':
                if ($opnd->mem->segment != 0) {
                    throw new Exception();
                }

                $this->operands[] = new MemOpnd(
                    new RegOpnd($opnd->mem->base, 4),
                    is_string($opnd->mem->index) ? new RegOpnd($opnd->mem->index, 4) : 0,
                    $opnd->mem->scale,
                    $opnd->mem->disp,
                    $opnd->size,
                    $state->esp
                );
                break;
            case 'imm':
                $this->operands[] = new ImmOpnd($opnd->imm, $opnd->size);
                break;
            default:
                dump($opnd);
                throw new Exception(
                    sprintf("Invalid Operand %d: %s",
                        count($this->operands),
                        $opnd->type
                    )
                );
            }
        }
    }

}
