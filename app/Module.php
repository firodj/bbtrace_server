<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    public $timestamps = false;

    public function subroutines()
    {
        $this->hasMany(Subroutine::class);
    }
}

