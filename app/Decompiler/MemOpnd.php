<?php

namespace App\Decompiler;

use Exception;

class MemOpnd extends BaseOperand
{
    public $base;
    public $index;
    public $scale;
    public $disp;
    public $var = null;

    public function __construct($base, $index, $scale, $disp, $size, $esp, $ebp) {
        parent::__construct($size);
        $this->base = $base;
        $this->index = $index;
        $this->scale = $scale;
        $this->disp = $disp;
        $this->size = $size;

        if ($this->base instanceof RegOpnd) {
            if ($this->base->reg === 'esp') {
                $this->var = $esp + $disp;
            }
            if ($this->base->reg === 'ebp') {
                $this->var = $ebp + $disp;
            }
        }
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
        return ($this->isStack() || $this->isFrame()) && $this->var < 0;
    }

    public function isArg()
    {
        return ($this->isStack() || $this->isFrame()) && $this->var >= 0;
    }

    public function isFrame()
    {
        return $this->base instanceof RegOpnd && $this->base->reg === 'ebp';
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
        if (isset($this->display_name)) return $this->display_name;

        if ($this->isVar()) {
            $content = sprintf("*ptr_var_%d", -$this->var);
        } else if ($this->isArg()) {
            if ($this->var >= 4) {
                $content = sprintf("*ptr_arg_%d", $this->var - 4);
            } else {
                $content = sprintf("*ptr_ret_%d", $this->var);
            }
        } else if ($this->isMem()) {
            $content = sprintf("*data_%x", $this->disp);
        } else {
            $content = sprintf("*(%s)", $this->getContent());
        }
        if ($this->size == 1) {
            return sprintf("(byte)%s", $content);
        } else
        if ($this->size == 2) {
            return sprintf("(word)%s", $content);
        } else 
        if ($this->size == 4) {
            return sprintf("(dword)%s", $content);
        }

        throw new Exception();
    }
}
