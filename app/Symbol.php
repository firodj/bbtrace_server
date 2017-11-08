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
        return $this->morphMany(Flow::class, 'block');
    }

    public function nextFlows()
    {
        return $this->morphMany(Flow::class, 'last_block');
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
