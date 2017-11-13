<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use App;

class Expression
{
    public function saving(App\Expression $expr)
    {
        if (is_null($expr->operand_id)) {
            if ($expr->parent_id) {
                $expr->operand_id = $expr->parent()->first()->operand_id;
            }
        }
    }

    public function deleted(App\Expression $expr)
    {
        foreach ($expr->expressions()->get() as $subexpr) {
            $subexpr->delete();
        }
    }
}
