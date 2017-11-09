<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Subroutine extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'returns' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function blocks()
    {
        return $this->hasMany(Block::class);
    }

    public function getSize()
    {
        return $this->end - $this->addr;
    }

    public function getRva()
    {
        return $this->addr - $this->module->addr;
    }
}

