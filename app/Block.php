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
        return $this->hasMany(Flow::class, 'id');
    }

    public function nextFlows()
    {
        return $this->hasMany(Flow::class, 'last_block_id');
    }

    public function getSize()
    {
        return $this->end - $this->id;
    }

    public function getRva()
    {
        return $this->id - $this->module_id;
    }

    public function getDisplayName()
    {
        if ($this->subroutine) {
            $name = $this->subroutine->name;
            if ($this->id != $this->subroutine_id) {
                $ofs = dechex(abs($this->id - $this->subroutine_id));
                $name .= ($this->id < $this->subroutine_id ? '-' : '+' ) . $ofs;
            }
            return $name;
        }
        return dechex($id);
    }
}

