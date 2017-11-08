<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function subroutines()
    {
        $this->hasMany(Subroutine::class);
    }
}

