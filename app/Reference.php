<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Reference extends Model
{
    /**
     * BasicBlock model should not be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
    public $incrementing = false;

    protected $guarded = [];

    public function block()
    {
        return $this->belongsTo(Block::class);
    }
}

