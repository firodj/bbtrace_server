<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Instruction extends Model
{
    public $timestamps = false;

    protected $guarded = [];

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
        $op_strs = $this->operands->map(function ($op) { return $op->toString(); });

        if ($op_strs->count() > 0) {
            $op_str = ' ' . implode(', ', $op_strs->toArray());
        } else {
            $op_str = '';
        }

        return $this->mne . $op_str;
    }
}
