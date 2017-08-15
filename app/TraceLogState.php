<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TraceLogState extends Model
{
    /**
     * BasicBlock model should not be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $guarded = [];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'stacks' => 'array',
    ];

    public function lastBlock()
    {
        return $this->belongsTo(Block::class);
    }

}

