<?php

namespace App\Decompiler;

use Exception;

abstract class BaseMnemonic
{
    public $ins;
    public $operands;
    public $outputs;
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
        $this->outputs = [];
        $this->detail = $ins->detail->x86;
        $this->block_id = $block_id;
        $this->ast = [];
    }

    abstract public function process($state);

    abstract function toString($options = []);

    public function getReads() {
        $reads = [];
        foreach ($this->reads as $reg => $opnd) {
            // RegOpnd and FlagOpnd
            $reads[] = (string) $opnd;
        }
        return $reads;
    }
    public function getWrites() {
        $writes = [];
        foreach ($this->writes as $reg => $opnd) {
            // RegOpnd and FlagOpnd
            $writes[] = (string) $opnd;
        }
        return $writes;
    }

    public function __toString()
    {
        return $this->toString();
    }

    public function detectReadsWrites()
    {
        foreach ($this->operands as $opnd) {
            if ($opnd instanceof RegOpnd) {
                if ($opnd->is_read) {
                    $this->reads[$opnd->reg] = $opnd;
                }
                if ($opnd->is_write) {
                    $opnd->is_write = false;

                    $opnd2 = clone $opnd;
                    $opnd2->is_read = false;

                    $this->outputs[$opnd2->reg] = $opnd2;
                    $this->writes[$opnd2->reg] = $opnd2;
                }
            }

            if ($opnd instanceof MemOpnd) {
                if ($opnd->base instanceof RegOpnd) {
                    $this->reads[$opnd->base->reg] = $opnd->base;
                }
                if ($opnd->index instanceof RegOpnd) {
                    $this->reads[$opnd->index->reg] = $opnd->index;
                }
            }
        }

        $eflags = $this->detail->eflags;
        foreach ($eflags->test as $f) {
            if (! array_key_exists($f, $this->reads)) {
                $opnd = new FlagOpnd($f);
                $opnd->is_read = true;
                $this->reads[$f] = $opnd;
            }

        }
        $flag_writes = $eflags->modify + $eflags->reset + $eflags->set;
        foreach($flag_writes as $f) {
            if (! array_key_exists($f, $this->writes)) {
                $opnd = new FlagOpnd($f);
                $opnd->is_write = true;
                $this->writes[$f] = $opnd;
            }
        }

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
                if ($operand->isArg() && ($state->arg < $operand->var)) {
                    $state->arg = $operand->var;
                }
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
