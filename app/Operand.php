<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Exception;

class Operand extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function instruction()
    {
        return $this->belongsTo(Instruction::class);
    }

    public function expression()
    {
        return $this->belongsTo(Expression::class);
    }

    public function memNormalize()
    {
        if ($this->type != 'mem') return;

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
        case 'reg':
            return $this->reg;
        case 'imm':
            return sprintf('%d', $this->imm);
        case 'mem':
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
                if (count($x) > 0) $x[] = '+';
                $x[] = sprintf('%d', $this->imm);
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
        return ($this->type == 'mem' &&
            is_null($this->index) &&
            is_null($this->reg)
        );
    }

    public function memIsIndirect(): bool {
        return ($this->type == 'mem' &&
            is_null($this->index) &&
            empty($this->imm)
        );
    }
}
