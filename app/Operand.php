<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Exception;

class Operand extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    const REG_TYPE = 'reg';
    const IMM_TYPE = 'imm';
    const MEM_TYPE = 'mem';

    public function instruction()
    {
        return $this->belongsTo(Instruction::class);
    }

    public function expression()
    {
        return $this->hasOne(Expression::class)->orderBy('pos', 'asc')->whereNull('parent_id');
    }

    public function memNormalize()
    {
        if ($this->type != self::MEM_TYPE) return;

        if (! $this->reg) {
            // move index into base when no scale
            if ($this->index) {
                if (!$this->scale) {
                    $this->reg = $this->index;
                    $this->index = null;
                }
            } else {
                // no base and index, and disp also null
                if (is_null($this->imm)) {
                    $this->imm = 0;
                }
            }
        }
    }

    public function toString()
    {
        switch ($this->type) {
        case self::REG_TYPE:
            return $this->reg;
        case self::IMM_TYPE:
            return sprintf('%d', $this->imm);
        case self::MEM_TYPE:
            $x = [];
            if ($this->reg) {
                $x[] = $this->reg;
            }
            if ($this->index) {
                if (count($x) > 0) $x[] = '+';
                $x[] = $this->index;

                if ($this->scale) {
                    $x[] = '*';
                    $x[] = sprintf('%d', $this->scale);
                }
            }
            if ($this->imm) {
                if (count($x) > 0) {
                    if ($this->imm > 0) {
                        $x[] = sprintf('+ %d', $this->imm);
                    } else {
                        $x[] = sprintf('- %d', abs($this->imm));
                    }
                } else {
                    $x[] = sprintf('%d', $this->imm);
                }
            }

            switch ($this->size) {
            case 8:
                return sprintf('byte ptr [%s]', implode(' ', $x));
            case 16:
                return sprintf('word ptr [%s]', implode(' ', $x));
            case 32:
                return sprintf('dword ptr [%s]', implode(' ', $x));
            case 64:
                return sprintf('qword ptr [%s]', implode(' ', $x));
            default:
                throw new Exception('Unknown memory operand size');
            }
        }
    }

    public function memIsDirect(): bool {
        return ($this->type == self::MEM_TYPE &&
            is_null($this->index) &&
            is_null($this->reg)
        );
    }

    public function memIsIndirect(): bool {
        return ($this->type == self::MEM_TYPE &&
            is_null($this->index) &&
            empty($this->imm)
        );
    }

    public function isEqual($opnd): bool {
        if ($this->type != $opnd->type) return false;
        if ($this->size != $opnd->size) return false;
        switch ($this->type) {
        case self::REG_TYPE:
            if ($this->reg != $opnd->reg) return false;
            break;
        case self::IMM_TYPE:
            if ($this->imm != $opnd->imm) return false;
            break;
        case self::MEM_TYPE:
            if ($this->reg != $opnd->reg ||
                $this->imm != $opnd->imm ||
                $this->index != $opnd->index ||
                $this->scale != $opnd->scale ||
                $this->seg != $opnd->seg)
                    return false;
            break;
        default:
            return false;
        }
        return true;
    }

    public function isImm($imm = null, $size = null): bool {
        if ($this->type != self::IMM_TYPE) return false;

        if (!is_null($size) && $this->size != $size) return false;

        if (is_null($imm)) return true;
        if (is_null($size)) $size = $this->size;

        if ($this->imm == $imm) return true;
        if (self::makeSigned($this->imm, $size) == $imm) return true;

        return false;
    }

    public static function makeSigned($imm, $size) {
        if ($size == 0 || $imm == 0) return $imm;

        if ($imm < 0) {
            return $imm;
        }

        $sign = $imm >> ($size - 1);
        if (($sign & 1) == 1) {
            return $imm - (1 << $size);
        }

        return $imm;
    }
}
