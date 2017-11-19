<?php

use App\Services\DefUseAnalyzer;
use App\Services\Decompiler\State;
use App\Block;
use App\Instruction;

class DefUseAnalyzerTest extends TestCase
{
    public function setUp()
    {
        $this->state = State::createState();
    }

    protected function analInst($mne)
    {
        $inst = Instruction::where('mne', $mne)->firstOrFail();

        fprintf(STDERR, "#%d: %s\n", $inst->id, $inst->toString());

        $anal = new DefUseAnalyzer($inst, $this->state);

        $anal->analyze();

        $this->inst = $inst;
        $this->anal = $anal;
    }

    public function testDoMov()
    {
        $this->analInst('mov');

        // mov esi, eax

        $this->assertContains($this->inst->id, $this->state->latestDef('eax')->uses);
        $this->assertEquals($this->inst->id, $this->state->latestDef('esi')->inst_id);
    }

    public function testDoPush()
    {
        $before_esp = $this->state->latestDef('esp');

        $this->analInst('push');

        // push esi

        $this->assertContains($this->inst->id, $this->state->latestDef('esi')->uses);
        $this->assertContains($this->inst->id, $before_esp->uses);
        $this->assertEquals($this->inst->id, $this->state->latestDef('esp')->inst_id);
    }

    public function testDoPop()
    {
        $before_esp = $this->state->latestDef('esp');

        $this->analInst('pop');

        // pop esi

        $this->assertContains($this->inst->id, $before_esp->uses);
        $this->assertEquals($this->inst->id, $this->state->latestDef('esp')->inst_id);
        $this->assertEquals($this->inst->id, $this->state->latestDef('esi')->inst_id);
    }
}
