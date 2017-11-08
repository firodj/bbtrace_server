<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Symbol extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function flows()
    {
        return $this->hasMany(Flow::class, 'id');
    }

    public function nextFlows()
    {
        return $this->hasMany(Flow::class, 'last_block_id');
    }

    public function getDisplayName()
    {
        if ($this->module) {
            $name = $this->module->name.'!';
        } else {
            $name = dechex($this->module->addr).'!';
        }
        $name .= $this->name;

        if ($this->ordinal) {
            $name .= '@'.$this->ordinal;
        }
        return $name;
    }

}
