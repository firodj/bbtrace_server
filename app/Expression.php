<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Expression extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    const MEMORY_TYPE = 'memory';
    const CONST_TYPE = 'const';
    const PTR_TYPE = 'ptr';

    public function operand()
    {
        return $this->belongsTo(Operand::class);
    }

    public function children()
    {
        return $this->hasMany(Expression::class, 'parent_id')->orderBy('pos', 'asc');
    }

    public function toString()
    {
    }
}
