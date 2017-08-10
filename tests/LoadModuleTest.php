<?php

class LoadModuleTest extends TestCase
{
    public function testCreate()
    {
        $m = new App\LoadModule;
        $m->id = 0x400000;
        $m->entry = 0x0041e4cf;
        $m->end = 0x00729000;
        $m->name = "psxfin.exe";
        $m->path = "C:\\Games\\pSX_1_13\\psxfin.exe";
        $m->save();

        $this->assertTrue($m->exists);
    }
}
