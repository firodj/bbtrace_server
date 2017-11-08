<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function lastBlock()
    {
        return $this->belongsTo(Block::class);
    }

    public function lastSymbol()
    {
        return $this->belongsTo(Symbol::class, 'last_block_id');
    }

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function symbol()
    {
        return $this->belongsTo(Symbol::class, 'block_id');
    }
}

