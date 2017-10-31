<?php

namespace App\Decompiler;

use Exception;
use App\Subroutine;

class CallMne extends BaseMnemonic
{
    public $goto = null;
    public $subroutine = null;
    public $symbol = null;
    public $esp = null;

    const API_ESP = [
        'ntdll.dll' => [
            'RtlAllocateHeap' => 3 * 4 // ordinal 645
        ],
        'kernel32.dll' => [
            'FlsGetValue' => 1 * 4, // ordinal 342
            'GetLastError' => 0, // ordinal 514
            'TlsGetValue' => 1 * 4, // ordinal 1227
            'SetLastError' => 1 * 4, // ordinal 1140
        ]
    ];

    public function process($state)
    {
        $operands = $this->operands;

        if ($operands[0] instanceof ImmOpnd) {
            $this->goto = $operands[0];
            $this->subroutine = Subroutine::find($this->goto);
            if ($this->subroutine) {
                if (is_null($this->subroutine->esp)) {
                    throw new Exception("Subroutine not analyzed");
                }
                $this->esp = $this->subroutine->esp;
                $state->esp += $this->subroutine->esp;
            } else {
                throw new Exception("Call to undefined subroutine");
            }
        }

        return $state;
    }

    public function afterProcess($block, $analyzer, $state)
    {
        if ($block->goto) return;

        if ($block->jump_addr !== $this->ins->address) return;

        foreach($block->nextFlows as $flow) {
            $this->symbol = $flow->symbol;
            if ($this->symbol) {
                $this->esp = $this->apiCall($this->symbol);
                if ($this->esp) {
                    $state->esp += $this->esp;
                }
            }
        }

        return $state;
    }

    public function toString($options = [])
    {
        $operands = $this->operands;

        if ($this->subroutine) {
            return sprintf("call %s with pop(%d)", $this->subroutine->name, $this->esp);
        }
        if ($this->symbol) {
            return sprintf("call %s with pop(%d)", $this->symbol->getDisplayName(), $this->esp);
        }
        return sprintf("call %s", $this->goto->toString(['hex']));
    }

    public function apiCall($symbol) {
        $module = strtolower($symbol->module->name);
        //if (!isset(self::API_ESP[$module])) throw new Exception($symbol->getDisplayName());
        if (!isset(self::API_ESP[$module][$symbol->name])) throw new Exception($symbol->getDisplayName());
        return self::API_ESP[$module][$symbol->name];
    }
}
