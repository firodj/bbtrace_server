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

    protected $guarded = [];

    protected $casts = [
        'codes' => 'array',
    ];

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

    public function flows()
    {
        return $this->morphMany(Flow::class, 'block');
    }

    public function nextFlows()
    {
        return $this->morphMany(Flow::class, 'last_block');
    }

    public function getSize()
    {
        return $this->end - $this->addr;
    }

    public function getRva()
    {
        return $this->addr - $this->module->addr;
    }

    public function getDisplayName()
    {
        if ($this->subroutine) {
            $name = $this->subroutine->name;
            if ($this->addr != $this->subroutine->addr) {
                $ofs = dechex(abs($this->addr - $this->subroutine->addr));
                $name .= ($this->addr < $this->subroutine->addr ? '-' : '+' ) . $ofs;
            }
            return $name;
        }
        return 'block_'.dechex($this->addr);
    }
}

