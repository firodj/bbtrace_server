<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function block()
    {
        return $this->morphTo();
    }

    public function lastBlock()
    {
        return $this->morphTo();
    }
}

