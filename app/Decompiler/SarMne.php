<?php

namespace App\Decompiler;

use Exception;

class SarMne extends BaseMnemonic
{
    public function process()
    {
        $state = $this->state;
        $operands = $this->operands;

        printf("%s >>= %s // cf\n", $operands[0], $operands[1]);

        return $state;
    }
}
