<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Symbol extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $guarded = [];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
