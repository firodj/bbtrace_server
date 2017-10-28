<?php

namespace App\Decompiler;

class State
{
    public $esp;
    public $reg_changes = [];
    public $reg_imports = [];
    public $esp_stack = [];

    public function __construct()
    {
        $this->esp = 0;
    }

    public function checkReadsWrites($mne)
    {
        $address = $mne->ins->address;
        $block_id = $mne->block_id;

        foreach ($mne->reads as $read)
        {
            if (!isset($this->reg_changes[$read])) {
                if (!isset($this->reg_imports[$read])) $this->reg_imports[$read] = [];
                $this->reg_imports[$read][] = (object)[
                    'address' => $address,
                    'block_id' => $block_id
                ];
            }
        }
        foreach ($mne->writes as $write)
        {
            $this->reg_changes[$write] = (object)[
                'address' => $address,
                'block_id' => $block_id
            ];
        }
    }

    public function pushStack($opnd)
    {
        $this->esp -= 4;
        $this->esp_stack[$this->esp] = clone $opnd;
    }

    public function popStack()
    {
        $opnd = $this->esp_stack[$this->esp];
        $this->esp += 4;

        return $opnd;
    }
}
