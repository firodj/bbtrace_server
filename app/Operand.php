<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Operand extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function instruction()
    {
        return $this->belongsTo(Instruction::class);
    }

    public function toString()
    {
        switch ($this->type) {
        case 'reg':
            return $this->reg;
        case 'imm':
            return sprintf('0x%x', $this->imm);
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
                $x[] = sprintf('0x%x', $this->imm);
            }

            switch ($this->size) {
            case 1:
                return sprintf('byte ptr [%s]', implode(' ', $x));
            case 2:
                return sprintf('word ptr [%s]', implode(' ', $x));
            case 4:
                return sprintf('dword ptr [%s]', implode(' ', $x));
            }
        }
    }

}
