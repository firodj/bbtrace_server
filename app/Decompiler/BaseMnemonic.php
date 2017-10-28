<?php

namespace App\Decompiler;

use Exception;

abstract class BaseMnemonic
{
    public $ins;
    public $operands;
    public $detail;
    public $writes;
    public $reads;
    public $block_id;
    public $ast;

    public function __construct($block_id, $ins)
    {
        $this->ins = $ins;
        $this->reads = [];
        $this->writes = [];
        $this->detail = $ins->detail->x86;
        $this->block_id = $block_id;
        $this->ast = [];
    }

    abstract public function process($state);

    abstract function toString($options = []);

    public function __toString()
    {
        $s = $this->toString();

        $s .= ' w:'.json_encode($this->writes);
        $s .= ' r:'.json_encode($this->reads);

        return $s;
    }

    public function detectReadsWrites()
    {
        foreach ($this->operands as $opnd) {
            if ($opnd instanceof RegOpnd) {
                if ($opnd->is_write) {
                    $this->writes[] = $opnd->reg;
                }
                if ($opnd->is_read) {
                    $this->reads[] = $opnd->reg;
                }
            }

            if ($opnd instanceof MemOpnd) {
                if ($opnd->base instanceof RegOpnd) {
                    $this->reads[] = $opnd->base->reg;
                }
                if ($opnd->index instanceof RegOpnd) {
                    $this->reads[] = $opnd->index->reg;
                }
            }
        }

        $eflags = $this->detail->eflags;
        $this->writes += $eflags->modify + $eflags->reset + $eflags->set;
        $this->reads += $eflags->test;

        if (! empty($eflags->prior)) {
            throw new Exception("eflags prior has: ". implode(',', $eflags->prior));
        }
    }

    public function createOperands($state)
    {
        $operands = $this->ins->detail->x86->operands;
        $this->operands = [];

        foreach($operands as $opnd) {
            $operand = null;

            switch ($opnd->type) {
            case 'reg':
                $operand = new RegOpnd($opnd->reg, $opnd->size);
                break;
            case 'mem':
                if ($opnd->mem->segment != 0) {
                    throw new Exception();
                }

                $operand = new MemOpnd(
                    new RegOpnd($opnd->mem->base, 4),
                    is_string($opnd->mem->index) ? new RegOpnd($opnd->mem->index, 4) : 0,
                    $opnd->mem->scale,
                    $opnd->mem->disp,
                    $opnd->size,
                    $state->esp
                );
                break;
            case 'imm':
                $operand = new ImmOpnd($opnd->imm, $opnd->size);
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

            if (in_array('read', $opnd->access)) $operand->is_read = true;
            if (in_array('write', $opnd->access)) $operand->is_write = true;

            $this->operands[] = $operand;
        }
    }
}
