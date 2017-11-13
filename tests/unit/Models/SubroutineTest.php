<?php

class SubroutineTest extends TestCase
{
    public function testCreate()
    {
        $sub = new App\Subroutine;
        $sub->addr = 0x401370;
        $sub->end = 0x401377;
        $sub->module_id = 1;
        $sub->name = '?seekpos@?$fpos@H@std@@QBE_JXZ';
        $sub->save();

        $this->assertTrue($sub->exists);
    }
}
