<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Instruction extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<string> $hidden
     */
    protected $hidden = ['opcodes'];

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function operands()
    {
        return $this->hasMany(Operand::class)->orderBy('pos', 'asc');
    }

    public function toString()
    {
        $x = $this->operands->map(function ($op) { return $op->toString(); });

        $s = $this->mne;

        if (count($x)) {
            $s .= ' ' . $x->implode(', ');
        }

        return $s;
    }

    public function toExpressionString()
    {
        $x = $this->operands->map(function ($op) { return $op->toString(); });

        switch ($this->mne) {
        case 'mov':
            return sprintf("%s = %s", $x[0], $x[1]);
        case 'sub':
            return sprintf("%s = %s - %s", $x[0], $x[0], $x[1]);
        case 'sbb':
            if ($this->operands[0]->isEqual($this->operands[1])) {
                return sprintf("%s = -%s", $x[0], $cf);
            } else {
                return sprintf("%s = %s - (%s + %s)", $x[0], $x[0], $x[1], $cf);
            }
        case 'add':
            return sprintf("%s = %s + %s", $x[0], $x[0], $x[1]);
        case 'and':
            return sprintf("%s = %s & %s", $x[0], $x[0], $x[1]);
        case 'xor':
            if ($this->operands[0]->isEqual($this->operands[1])) {
                return sprintf("%s = 0", $x[0]);
            }
            return sprintf("%s = %s ^ %s", $x[0], $x[0], $x[1]);
        case 'or':
            if ($this->operands[1]->isImm(-1)) {
                return sprintf("%s = -1", $x[0]);
            }
            return sprintf("%s = %s | %s", $x[0], $x[0], $x[1]);
        case 'movzx':
            return sprintf("%s = zx(%s)", $x[0], $x[1]);
        case 'movsx':
            return sprintf("%s = sx(%s)", $x[0], $x[1]);
        case 'shl':
            return sprintf("%s = %s << %s", $x[0], $x[0], $x[1]);
        case 'shr':
            return sprintf("%s = %s >> %s", $x[0], $x[0], $x[1]);
        case 'sar':
            return sprintf("%s = %s >-> %s", $x[0], $x[0], $x[1]);
        case 'sal':
            return sprintf("%s = %s <-< %s", $x[0], $x[0], $x[1]);
        case 'neg':
            return sprintf("%s = -%s", $x[0], $x[0]);
        case 'lea':
            return sprintf("%s = %s", $x[0], $this->operands[1]->expression->expressions[0]->toString());
        case 'fld':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s // push", $st0, $x[0]);
            }
            break;
        case 'fild':
            if ($this->operands->count() == 1) {
                return sprintf("%s = x(%s) // push", $st0, $x[0]);
            }
            break;
        case 'fdiv':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s / %s", $st0, $st0, $x[0]);
            }
            break;
        case 'fidiv':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s / x(%s)", $st0, $st0, $x[0]);
            }
            break;
        case 'fdivp':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s / %s // pop", $st0, $st0, $x[0]);
            }
            break;
        case 'fmul':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s * %s", $st0, $st0, $x[0]);
            }
            break;
        case 'fimul':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s * x(%s)", $st0, $st0, $x[0]);
            }
            break;
        case 'fmulp':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s * %s // pop", $st0, $st0, $x[0]);
            }
            break;
        case 'fsub':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s - %s", $st0, $st0, $x[0]);
            }
            break;
        case 'fisub':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s - x(%s)", $st0, $st0, $x[0]);
            }
            break;
        case 'fsubp':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s - %s // pop", $st0, $st0, $x[0]);
            }
            break;
        case 'fst':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s", $x[0], $st0);
            }
            break;
        case 'fstp':
            if ($this->operands->count() == 1) {
                return sprintf("%s = %s // pop", $x[0], $st0);
            }
            break;
        case 'fist':
            if ($this->operands->count() == 1) {
                return sprintf("x(%s) = %s", $x[0], $st0);
            }
            break;
        case 'fistp':
            if ($this->operands->count() == 1) {
                return sprintf("x(%s) = %s // pop", $x[0], $st0);
            }
            break;
        }

        if ($x->count() > 0) {
            return sprintf("%s %s", $this->mne, $x->implode(', '));
        }
        return $this->mne;
    }
}
