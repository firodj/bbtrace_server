<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Expression extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    const MEMORY_TYPE = 'memory';
    const CONST_TYPE = 'const';
    const DEREF_TYPE = 'deref';
    const ADD_TYPE = 'add';
    const MUL_TYPE = 'mul';

    public function operand()
    {
        return $this->belongsTo(Operand::class);
    }

    public function parent()
    {
        return $this->belongsTo(Expression::class);
    }

    public function children()
    {
        return $this->hasMany(Expression::class, 'parent_id')->orderBy('pos', 'asc');
    }

    public function addChild(Expression $child)
    {
        $query = $this->children()->select(app('db')->raw('max(pos) as max_pos'));
        $max_pos = $query->first();

        if ($max_pos && !is_null($max_pos->max_pos)) $pos = $max_pos->max_pos + 1;
        else $pos = 0;

        $child->pos = $pos;
        $child->save();

        $this->children()->save($child);
    }

    public function toString()
    {
    }
}
