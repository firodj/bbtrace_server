<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    /**
     * BasicBlock model should not be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function subroutine()
    {
        return $this->belongsTo(Subroutine::class);
    }
}

