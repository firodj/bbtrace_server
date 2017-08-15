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
    public $incrementing = false;

    protected $guarded = [];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function subroutine()
    {
        return $this->belongsTo(Subroutine::class);
    }

    public function references()
    {
        return $this->hasMany(Reference::class);
    }

    public function getSize()
    {
        return $this->end - $this->id;
    }

    public function getRva()
    {
        return $this->id - $this->module_id;
    }
}

