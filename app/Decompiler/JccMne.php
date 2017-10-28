<?php

namespace App\Decompiler;

use Exception;

class JccMne extends BaseMnemonic
{
    public function process()
    {
        $state = $this->state;
        $operands = $this->operands;

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        $else = $this->ins->address + count($this->ins->bytes);

        switch ($this->ins->mnemonic) {
        case 'je':
        case 'jz':
            $content = "a == b";
            break;
        case 'jne':
        case 'jnz':
            $content = "a != b";
            break;
        case 'jle':
            $content = "(signed)a <= (signed)b";
            break;
        case 'jg':
            $content = "(signed)a > (signed)b";
            break;
        case 'ja':
        case 'jnbe':
            $content = "(unsigned)a > (unsigned)b";
            break;
        default:
            dump($this->ins);
            throw new Exception($this->ins->mnemonic);
        }

        return sprintf("if (%s) goto %s else goto 0x%x", $content, $operands[0]->toString(['hex']), $else);
    }
}
