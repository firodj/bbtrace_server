<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use App;

class Instruction
{
    public function deleted(App\Instruction $inst)
    {
        foreach ($inst->operands()->get() as $opnd) {
            $opnd->delete();
        }
    }
}
