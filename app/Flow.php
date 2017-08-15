<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    /**
     * BasicBlock model should not be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
    public $incrementing = false;

    protected $guarded = [];

    public function lastBlock()
    {
        return $this->belongsTo(Block::class);
    }

    public function lastSymbol()
    {
        return $this->belongsTo(Symbol::class, 'last_block_id');
    }
}

