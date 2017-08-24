<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GraphLink extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function source()
    {
        return $this->belongsTo(GraphNode::class);
    }

    public function target()
    {
        return $this->belongsTo(GraphNode::class);
    }
}
