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

        fprintf(STDERR, "#%d: %s\n", $inst->id, $inst->toString());

        $anal = new InstructionAnalyzer($inst);

        $anal->analyze($state);

        $this->assertContains($inst->id, $state->reg_defs['eax']->latestDef()->uses);
        $this->assertEquals($inst->id, $state->reg_defs['esi']->latestDef()->inst_id);
    }
}
