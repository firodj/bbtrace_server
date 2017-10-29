<?php

namespace App\Decompiler;

use Exception;

class JccMne extends BaseMnemonic
{
    public $condition = null;

    public $is_signed = null;
    public $then = null;
    public $else = null;
    public $branch_taken = [];

    public function process($state)
    {
        $operands = $this->operands;

        if (!($operands[0] instanceof ImmOpnd)) {
            throw new Exception();
        }

        $this->else = $this->ins->address + count($this->ins->bytes);
        $this->then = $operands[0];

        switch ($this->ins->mnemonic) {
        case 'je':
        case 'jz':
            $this->condition = "==";
            break;
        case 'jne':
        case 'jnz':
            $this->condition = "!=";
            break;
        case 'jle':
            $this->condition = "<=";
            $this->is_signed = true;
            break;
        case 'jg':
            $this->condition = ">";
            $this->is_signed = true;
            break;
        case 'ja':
        case 'jnbe':
            $this->condition = ">";
            $this->is_signed = false;
            break;
        default:
            dump($this->ins);
            throw new Exception($this->ins->mnemonic);
        }

        return $state;
    }

    public function afterProcess($block, $analyzer)
    {
        if (! $block->jump_dest) return;
        if ($block->jump_addr !== $this->ins->address) return;

        foreach($block->nextFlows as $flow) {
            if ($flow->id == $this->else) {
                $this->branch_taken[] = 'else';
            } else if ($flow->id == $this->then->imm) {
                $this->branch_taken[] = 'then';
            } else {
                throw new Exception();
            }
        }

        if (empty($this->branch_taken)) {
            throw new Exception();
        }

        // find where cmp command, and make sure
        // all flags changes by one instruction
        $cmp_by = null;
        foreach ($this->reads as $reg => $opnd) {
            $reg_cmp_by = $analyzer->reg_revisions[$reg][$opnd->rev]->write_by;

            if ($cmp_by && $reg_cmp_by) {
                if ($cmp_by != $reg_cmp_by) {
                    throw new Exception;
                }
            }

            $cmp_by = $reg_cmp_by;
        }

        if ($cmp_by) {
            $cmp_mne = $analyzer->mnemonics[$cmp_by->block_id][$cmp_by->address];
            if ($cmp_mne->ins->mnemonic != 'cmp') throw new Exception();

            $this->operands[1] = $cmp_mne->operands[0];
            $this->operands[2] = $cmp_mne->operands[1];
        }
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        $sign = is_null($this->is_signed) ? '' :
            ($this->is_signed === true ? '(signed)' : '(unsigned)');

        $logical = sprintf("%s%s %s %s%s",
            $sign,
            $this->operands[1] ?? 'a',
            $this->condition,
            $sign,
            $this->operands[2] ?? 'b'
        );

        if (['then'] == $this->branch_taken) {
            return sprintf("assert (%s) goto 0x%x",
                $logical,
                $this->then->toString(['hex'])
            );
        } else
        if (['else'] == $this->branch_taken) {
            return sprintf("assert (!(%s)) goto 0x%x",
                $logical,
                $this->else
            );
        }

        return sprintf("if (%s) then goto %s else 0x%x",
            $logical,
            $this->then->toString(['hex']),
            $this->else
        );
    }
}
