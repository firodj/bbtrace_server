<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GraphNode extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function subroutine()
    {
        return $this->morphTo();
    }

    public function links()
    {
        return $this->hasMany(GraphLink::class, 'source_id');
    }

    public function prevLinks()
    {
        return $this->hasMany(GraphLink::class, 'target_id');
    }

    public function scopeCopies($query)
    {
        return $query->where('subroutine_id', $this->subroutine_id)
                     ->where('subroutine_type', $this->subroutine_type);
    }
}
