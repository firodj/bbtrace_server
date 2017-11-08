<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GraphNode extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function subroutine()
    {
        return $this->belongsTo(Subroutine::class);
    }

    public function links()
    {
        return $this->hasMany(GraphLink::class, 'source_id');
    }

    public function prevLinks()
    {
        return $this->hasMany(GraphLink::class, 'target_id');
    }

    public function copies()
    {
        return $this->hasMany(GraphNode::class, 'subroutine_id', 'subroutine_id');
    }
}
