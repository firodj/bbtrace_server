<?php

use App\Services\Decompiler\State;

class StateTest extends TestCase
{
    public function testDefs()
    {
        $state = State::createState();

        $state->defs(['eax','ecx'], 0);
        $state->defs(['eax','ebx'], 0);

        $this->assertEquals(1, $state->latestDef('eax')->rev);
        $this->assertEquals(1, $state->latestDef('ecx')->rev);
        $this->assertEquals(1, $state->latestDef('ebx')->rev);
        $this->assertEquals(0, $state->latestDef('edx')->rev);
    }

    public function testUses()
    {
        $state = State::createState();

        $state->defs(['eax'], 1);
        $state->defs(['eax'], 2);

        $state->uses(['eax'], 3);

        $this->assertContains(3, $state->latestDef('eax')->uses);
    }

    public function testUsesWithOverlap()
    {
        $state = State::createState();

        $state->defs(['al'], 1);
        $state->defs(['ah'], 2);

        $state->uses(['ax'], 3);

        $reg_defuse = $state->latestDef('ax');

        $this->assertEquals(0, $reg_defuse->rev);

        $reg_defuse = $state->latestDef('ah');
        $this->assertContains(3, $reg_defuse->uses);

        $reg_defuse = $state->latestDef('al');
        $this->assertContains(3, $reg_defuse->uses);
    }

    public function testUsesWithOverlap2()
    {
        $state = State::createState();

        $state->defs(['ax'], 1);
        $state->defs(['eax'], 2);
        $state->defs(['ah'], 3);

        $state->uses(['ax'], 4);

        $reg_defuse = $state->latestDef('ax');
        $this->assertNotContains(4, $reg_defuse->uses);

        $reg_defuse = $state->latestDef('ah');
        $this->assertContains(4, $reg_defuse->uses);

        $reg_defuse = $state->latestDef('eax');
        $this->assertContains(4, $reg_defuse->uses);
    }

    public function testClone()
    {
        $state = State::createState();
        $state->defs(['al'], 1);

        $state->uses(['ax'], 3);

        $state2 = clone $state;
        $state2->defs(['al'], 2);

        $state2->uses(['ax'], 4);

        $reg_defuse = $state->latestDef('al');
        $this->assertEquals(1, $reg_defuse->rev);
        $this->assertContains(3, $reg_defuse->uses);
        $this->assertNotContains(4, $reg_defuse->uses);

        $reg_defuse = $state2->latestDef('al');
        $this->assertEquals(2, $reg_defuse->rev);
        $this->assertContains(4, $reg_defuse->uses);
        $this->assertNotContains(3, $reg_defuse->uses);
    }
}
