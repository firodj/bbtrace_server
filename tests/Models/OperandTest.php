<?php

use App\Operand;

class OperandTest extends TestCase
{
    public function setUp()
    {
        $opnd = new Operand();
        $opnd->type = 'mem';
        $opnd->reg = 'eax';
        $opnd->size = 32;
        $opnd->index = 'ecx';
        $opnd->scale = 2;
        $opnd->imm = 40;

        $this->opnd_mem = $opnd;
    }

    public function testToStringWhenMem()
    {
        $opnd = $this->opnd_mem;

        $this->assertEquals("dword ptr [eax + ecx * 2 + 40]", $opnd->toString());

        $opnd->reg = null;

        $this->assertEquals("dword ptr [ecx * 2 + 40]", $opnd->toString());
    }

    public function testToStringWhenReg()
    {
        $opnd = new Operand();
        $opnd->type = 'reg';
        $opnd->reg = 'ah';

        $this->assertEquals("ah", $opnd->toString());
    }

    public function testToStringWhenImm()
    {
        $opnd = new Operand();
        $opnd->type = 'imm';
        $opnd->imm = 100;

        $this->assertEquals("100", $opnd->toString());
    }

    public function testMemNormalize()
    {
        $opnd = $this->opnd_mem;

        $opnd->reg = null;

        $opnd->memNormalize();

        $this->assertNull($opnd->reg);
        $this->assertNotNull($opnd->index);

        // ---
        $opnd->scale = null;

        $opnd->memNormalize();

        $this->assertNotNull($opnd->reg);
        $this->assertNull($opnd->index);

        // ---
        $opnd = new Operand();
        $opnd->type = 'mem';

        $this->assertNull($opnd->imm);

        $opnd->memNormalize();

        $this->assertNotNull($opnd->imm);
    }

    public function testMemIsDirect()
    {
        $opnd = $this->opnd_mem;

        $this->assertFalse($opnd->memIsDirect());

        // ---
        $opnd = new Operand();
        $opnd->type = 'mem';
        $opnd->imm = 0x401000;

        $this->assertTrue($opnd->memIsDirect());
    }

    public function testMemIsIndirect()
    {
        $opnd = $this->opnd_mem;

        $this->assertFalse($opnd->memIsDirect());

        // ---
        $opnd = new Operand();
        $opnd->type = 'mem';
        $opnd->reg = 'esi';

        $this->assertTrue($opnd->memIsIndirect());
    }

}
