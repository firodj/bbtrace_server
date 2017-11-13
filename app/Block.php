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

    public function instructions()
    {
        return $this->hasMany(Instruction::class)->orderBy('addr', 'asc');
    }

    public function statements()
    {
        return $this->hasMany(Statement::class)->orderBy('pos', 'asc');
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

    public function addStatement(Statement $stmt)
    {
        $query = $this->statements()->select(app('db')->raw('max(pos) as max_pos'));
        $max_pos = $query->first();

        if ($max_pos && !is_null($max_pos->max_pos)) $pos = $max_pos->max_pos + 1;
        else $pos = 0;

        $stmt->pos = $pos;
        $stmt->save();

        $this->statements()->save($stmt);
    }
}

