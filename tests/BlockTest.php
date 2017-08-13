<?php

class BlockTest extends TestCase
{
    public function testCreate()
    {
        $bb = new App\Block;
        $bb->id = 0x4285ce;
        $bb->module_id = 0x400000;
        $bb->end = 0x4285d6;
        $bb->save();

        $this->assertTrue($bb->exists);
    }
}
