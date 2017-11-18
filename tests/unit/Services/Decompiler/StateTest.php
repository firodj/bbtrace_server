<?php

use App\Services\Decompiler\State;

class StateTest extends TestCase
{
    public function testDefs()
    {
        $state = new State();

        $state->defs(['eax','ecx'], 0);
        $state->defs(['eax','ebx'], 0);

        $this->assertEquals(1, $state->regDef('eax')->latestDef()->rev);
        $this->assertEquals(1, $state->regDef('ecx')->latestDef()->rev);
        $this->assertEquals(0, $state->regDef('edx')->latestDef()->rev);
    }

    public function testUses()
    {
        $state = new State();

        $state->defs(['eax'], 1);
        $state->defs(['eax'], 2);

        $state->uses(['eax'], 3);

        $this->assertContains(3, $state->regDef('eax')->latestDef()->uses);
    }

    public function testUsesWithOverlap()
    {
        $state = new State();

        $state->defs(['al'], 1);
        $state->defs(['ah'], 2);

        $state->uses(['ax'], 3);

        $reg_defuse = $state->regDef('ax')->latestDef();

        $this->assertEquals(0, $reg_defuse->rev);

        $reg_defuse = $state->regDef('ah')->latestDef();
        $this->assertContains(3, $reg_defuse->uses);

        $reg_defuse = $state->regDef('al')->latestDef();
        $this->assertContains(3, $reg_defuse->uses);
    }

    public function testUsesWithOverlap2()
    {
        $state = new State();

        $state->defs(['ax'], 1);
        $state->defs(['eax'], 2);
        $state->defs(['ah'], 3);

        $state->uses(['ax'], 4);

        $reg_defuse = $state->regDef('ax')->latestDef();
        $this->assertNotContains(4, $reg_defuse->uses);

        $reg_defuse = $state->regDef('ah')->latestDef();
        $this->assertContains(4, $reg_defuse->uses);

        $reg_defuse = $state->regDef('eax')->latestDef();
        $this->assertContains(4, $reg_defuse->uses);
    }
}
