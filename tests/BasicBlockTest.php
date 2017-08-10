<?php

class BasicBlockTest extends TestCase
{
    public function testCreate()
    {
        $bb = new App\BasicBlock;
        $bb->id = 0x4285ce;
        $bb->load_module_id = 0x400000;
        $bb->end = 0x4285d6;
        $bb->save();

        $this->assertTrue($bb->exists);
    }
}
