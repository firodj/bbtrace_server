<?php

namespace App\Decompiler;

use Exception;
use App\Subroutine;
use App\Services\BbAnalyzer;

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

    public function afterProcess($block, $analyzer) {
    }

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
        foreach (array_keys($this->operands) as $i) {
            $opnd = $this->operands[$i];
            if ($opnd instanceof RegOpnd) {
                if ($opnd->is_read) {
                    if (! isset($this->reads[$opnd->reg])) {
                        $this->reads[$opnd->reg] = $opnd;
                    }
                }
                if ($opnd->is_write) {
                    $opnd->is_write = false;

                    $opnd2 = clone $opnd;
                    $opnd2->is_read = false;

                    if (isset($this->outputs[$opnd2->reg])) {
                        throw new Exception();
                    }

                    if (isset($this->writes[$opnd2->reg])) {
                        throw new Exception();
                    }

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
        $flag_writes = array_merge($eflags->modify, $eflags->reset, $eflags->set, $eflags->undefined);
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
            $addr = null;

            switch ($opnd->type) {
            case 'reg':
                $pos = 0;
                if ($opnd->size == 1) {
                    if ($opnd->reg == 'al') {
                        $reg = 'eax';
                    } else if ($opnd->reg == 'bl') {
                        $reg = 'ebx';
                    } else if ($opnd->reg == 'cl') {
                        $reg = 'ecx';
                    } else if ($opnd->reg == 'dl') {
                        $reg = 'edx';
                    } else
                    if ($opnd->reg == 'ah') {
                        $reg = 'eax';
                        $pos = 1;
                    } else if ($opnd->reg == 'bh') {
                        $reg = 'ebx';
                        $pos = 1;
                    } else if ($opnd->reg == 'ch') {
                        $reg = 'ecx';
                        $pos = 1;
                    } else if ($opnd->reg == 'dh') {
                        $reg = 'edx';
                        $pos = 1;
                    } else {
                        throw new Exception($opnd->reg);
                    }
                } else if ($opnd->size == 2) {
                    if ($opnd->reg == 'ax') {
                        $reg = 'eax';
                    } else if ($opnd->reg == 'bx') {
                        $reg = 'ebx';
                    } else if ($opnd->reg == 'cx') {
                        $reg = 'ecx';
                    } else if ($opnd->reg == 'dx') {
                        $reg = 'edx';
                    } else {
                        throw new Exception($opnd->reg);
                    }
                } else if ($opnd->size == 4) {
                    $reg = $opnd->reg;
                } else {
                    throw new Exception();
                }
                $operand = new RegOpnd($reg, $opnd->size);
                $operand->pos = $pos;
                break;
            case 'mem':
                if ($opnd->mem->segment != 0) {
                    throw new Exception();
                }

                $operand = new MemOpnd(
                    is_string($opnd->mem->base) ? new RegOpnd($opnd->mem->base, 4) : 0,
                    is_string($opnd->mem->index) ? new RegOpnd($opnd->mem->index, 4) : 0,
                    $opnd->mem->scale,
                    $opnd->mem->disp,
                    $opnd->size,
                    $state->esp
                );
                if ($operand->isArg() && ($state->arg < $operand->var)) {
                    $state->arg = $operand->var;
                }
                if ($operand->isMem()) {
                    $addr = $opnd->mem->disp;
                }
                break;
            case 'imm':
                $operand = new ImmOpnd($opnd->imm, $opnd->size);
                $addr = $opnd->imm;
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

            if ($addr) {
                $opnd->display_name = $this->getDisplayName($addr);
            }

            $this->operands[] = $operand;
        }
    }

    public function getDisplayName($addr)
    {
        $subroutine = Subroutine::find($addr);
        if ($subroutine) {
            return $subroutine->name;
        }

        $symbol = app(BbAnalyzer::class)->pe_parser->getSymbolByVA($addr);
        if ($symbol) {
            return sprintf("%s!%s", $sumbol[0], $symbol[1]);
        }
    }
}
