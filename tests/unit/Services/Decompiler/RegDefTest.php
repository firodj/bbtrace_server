<?php

use App\Services\Decompiler\RegDef;

class RegDefTest extends TestCase
{
    public function testCreateRegDefs()
    {
        $reg_defs = RegDef::createRegDefs();

        $this->assertEquals(52, count($reg_defs));
        $this->assertEquals(RegDef::class, get_class($reg_defs['eax']));
    }

    public function testRegShared()
    {
        $reg_shared = RegDef::regShared('eax');

        $this->assertEquals(['ax', 'al', 'ah'], array_values($reg_shared));
    }

    public function testRegOverlap()
    {
        $reg_overlap = RegDef::regOverlap('eax');

        $this->assertEquals(['ax', 'al', 'ah'], array_values($reg_overlap));

        $reg_overlap = RegDef::regOverlap('ax');

        $this->assertEquals(['eax', 'al', 'ah'], array_values($reg_overlap));

        $reg_overlap = RegDef::regOverlap('al');

        $this->assertEquals(['eax', 'ax'], array_values($reg_overlap));

        $reg_overlap = RegDef::regOverlap('ah');

        $this->assertEquals(['eax', 'ax'], array_values($reg_overlap));
    }


}
