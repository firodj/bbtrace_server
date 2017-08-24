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

    public function symbol()
    {
        return $this->belongsTo(Symbol::class, 'subroutine_id');
    }

    public function links()
    {
        return $this->hasMany(GraphLink::class, 'source_id');
    }
}
