<?php

class ImportSymbolTest extends TestCase
{
    public function testCreate()
    {
        $s = new App\ImportSymbol;
        $s->id = 0x77694f13;
        $s->load_module_id = 0x76e20000;
        $s->name = "FlsAlloc";
        $s->ordinal = 340;
        $s->save();

        $this->assertTrue($s->exists);
    }
}
