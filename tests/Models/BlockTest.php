<?php

class BlockTest extends TestCase
{
    public function testCreate()
    {
        $bb = new App\Block;
        $bb->addr = 0x4285ce;
        $bb->module_id = 1;
        $bb->end = 0x4285d6;
        $bb->save();

        $this->assertTrue($bb->exists);
    }
}
