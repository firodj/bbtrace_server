<?php

use App\Services\InstructionAnalyzer;
use App\Services\Decompiler\State;
use App\Block;
use App\Instruction;

class InstructionAnalyzerTest extends TestCase
{
    public function testDoMov()
    {
        $inst = Instruction::where('mne', 'mov')->first();

        $state = new State();
        $state->enter($inst->block_id);

        dump($inst->toString());

        $anal = new InstructionAnalyzer($inst);

        $anal->analyze($state);
    }
}
