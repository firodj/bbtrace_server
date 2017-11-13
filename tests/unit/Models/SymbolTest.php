<?php

class SymbolTest extends TestCase
{
    public function testCreate()
    {
        $s = new App\Symbol;
        $s->addr = 0x77694f13;
        $s->module_id = 0x76e20000;
        $s->name = "FlsAlloc";
        $s->ordinal = 340;
        $s->save();

        $this->assertTrue($s->exists);
    }
}
