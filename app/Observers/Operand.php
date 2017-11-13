<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use App;

class Operand
{
    public function deleted(App\Operand $opnd)
    {
        App\Expression::where('operand_id', $opnd->id)->delete();
    }
}
