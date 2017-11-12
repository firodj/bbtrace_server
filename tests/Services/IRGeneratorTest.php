i<?php

use App\Services\IRGenerator;
use App\Expression;
use App\Operand;

class IRGeneratorTest extends TestCase
{
    public function testMakeRegExpression()
    {
        $service = new IRGenerator();

        $expr = $service->makeRegExpression('eax');

        $this->assertEquals('memory', $expr->type);
        $this->assertEquals(1002, $expr->domain);
    }

    public function testMakeConstExpression()
    {
        $service = new IRGenerator();

        $expr = $service->makeConstExpression(-1, 32);

        $this->assertEquals('const', $expr->type);
        $this->assertEquals(-1, $expr->const);
    }

    public function testCreateExpressionFromOperandWhenDirect()
    {
        $opnd = new Operand();
        $opnd->size = 32;
        $opnd->type = 'mem';
        $opnd->imm = 100;

        $this->assertTrue($opnd->memIsDirect());
        $this->assertEquals("dword ptr [100]", $opnd->toString());

        $service = new IRGenerator();
        $expr = $service->createExpressionFromOperand($opnd);

        $this->assertEquals('memory', $expr->type);
        $this->assertEquals(1, $expr->domain);
        $this->assertEquals(100, $expr->const);
    }

    public function testCreateExpressionFromOperandWhenIsIndirect()
    {
        $opnd = new Operand();
        $opnd->size = 32;
        $opnd->type = 'mem';
        $opnd->reg = 'edi';

        $this->assertTrue($opnd->memIsIndirect());
        $this->assertEquals("dword ptr [edi]", $opnd->toString());

        $service = new IRGenerator();
        $expr = $service->createExpressionFromOperand($opnd);

        $this->assertEquals('deref', $expr->type);
        $this->assertEquals(1, $expr->children()->count());

        $this->assertEquals('memory', $expr->children[0]->type);
        $this->assertEquals(0, $expr->children[0]->pos);
        $this->assertEquals(1009, $expr->children[0]->domain);
    }

    public function testCreateExpressionFromOperandWhenDereference()
    {
        $opnd = new Operand();
        $opnd->size = 32;
        $opnd->type = 'mem';
        $opnd->reg = 'esi';
        $opnd->index = 'edx';
        $opnd->scale = 2;
        $opnd->imm = 20;

        $this->assertEquals("dword ptr [esi + edx * 2 + 20]", $opnd->toString());

        $service = new IRGenerator();
        $expr = $service->createExpressionFromOperand($opnd);

        $this->assertEquals('deref', $expr->type);
        $this->assertEquals(1, $expr->children()->count());

        $this->assertEquals('add', $expr->children[0]->type);
        $add_expr = $expr->children[0];

        $this->assertEquals(3, $add_expr->children()->count());

        $this->assertEquals('memory', $add_expr->children[0]->type);
        $this->assertEquals(0, $add_expr->children[0]->pos);
        $this->assertEquals('mul', $add_expr->children[1]->type);
        $this->assertEquals(1, $add_expr->children[1]->pos);
        $this->assertEquals('const', $add_expr->children[2]->type);
        $this->assertEquals(2, $add_expr->children[2]->pos);
    }
}
