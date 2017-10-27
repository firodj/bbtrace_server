<?php

namespace App\Decompiler;

class MemOpnd extends BaseOperand
{
    public $base;
    public $index;
    public $scale;
    public $disp;
    public $size;
    public $var;

    public function __construct($base, $index, $scale, $disp, $size, $esp) {
        $this->base = $base;
        $this->index = $index;
        $this->scale = $scale;
        $this->disp = $disp;
        $this->size = $size;
        $this->var = $esp + $disp;
    }

    public function getContent()
    {
        if ($this->index && $this->scale > 1) {
            $content = sprintf("%s + %s * %s", $this->base, $this->index, $this->scale);
        } else if ($this->index) {
            $content = sprintf("%s + %s", $this->base, $this->index);
        } else {
            $content = sprintf("%s", $this->base);
        }
        if ($this->disp) {
            $content = sprintf("%s + %s", $content, $this->disp);
        }
        return $content;
    }

    public function isVar()
    {
        return $this->isStack() && $this->var < 0;
    }

    public function isArg()
    {
        return $this->isStack() && $this->var >= 0;
    }

    public function isStack()
    {
        return $this->base instanceof RegOpnd && $this->base->reg === 'esp';
    }

    public function isMem()
    {
        return $this->base === 0 && $this->isOne();
    }

    public function isOne()
    {
        return $this->index === 0 && $this->scale === 1;
    }

    public function toString($options = []) {
        if ($this->isVar()) {
            $content = sprintf("var_%d", -$this->var);
        } else if ($this->isArg()) {
            if ($this->var >= 4) {
                $content = sprintf("arg_%d", $this->var - 4);
            } else {
                $content = sprintf("ret_%d", $this->var);
            }
        } else {
            $content = sprintf("global(%s)", $this->getContent());
        }
        if ($this->size == 4) {
            return sprintf("(dword)%s", $content);
        }

        throw new Exception();
    }
}
