<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DefUse extends Model
{
    protected $guarded = [];

    protected $increments = false;

    public $timestamps = false;

    public function instruction()
    {
        return $this->belongsTo(Instruction::class);
    }

    public function definedInstruction()
    {
        return $this->belongsTo(Instruction::class);
    }
}
