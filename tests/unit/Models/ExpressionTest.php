<?php

use App\Expression;
use App\Operand;

class ExpressionTest extends TestCase
{
    public function testMakeRegExpression()
    {
        $expr = Expression::makeRegExpression('eax');

        $this->assertEquals('memory', $expr->type);
        $this->assertEquals(1002, $expr->domain);
    }

    public function testMakeConstExpression()
    {
        $expr = Expression::makeConstExpression(-1, 32);

        $this->assertEquals('const', $expr->type);
        $this->assertEquals(-1, $expr->const);
    }

    public function testCreateExpressionFromOperandWhenDirect()
    {
        $opnd = new Operand();
        $opnd->size = 32;
        $opnd->type = 'mem';
        $opnd->imm = 100;
        $opnd->save();

        $this->assertTrue($opnd->memIsDirect());
        $this->assertEquals("dword ptr [100]", $opnd->toString());

        $expr = Expression::createExpressionFromOperand($opnd);
        $this->assertEquals($opnd->id, $expr->operand_id);

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
        $opnd->save();

        $this->assertTrue($opnd->memIsIndirect());
        $this->assertEquals("dword ptr [edi]", $opnd->toString());

        $expr = Expression::createExpressionFromOperand($opnd);

        $this->assertEquals('deref', $expr->type);
        $this->assertEquals(1, $expr->expressions()->count());

        $this->assertEquals('memory', $expr->expressions[0]->type);
        $this->assertEquals(0, $expr->expressions[0]->pos);
        $this->assertEquals(1009, $expr->expressions[0]->domain);
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
        $opnd->save();

        $this->assertEquals("dword ptr [esi + edx * 2 + 20]", $opnd->toString());

        $expr = Expression::createExpressionFromOperand($opnd);
        $this->assertEquals($opnd->id, $expr->operand_id);

        $this->assertEquals('deref', $expr->type);
        $this->assertEquals(1, $expr->expressions()->count());

        $this->assertEquals('add', $expr->expressions[0]->type);
        $add_expr = $expr->expressions[0];

        $this->assertEquals($opnd->id, $add_expr->operand_id);
        $this->assertEquals(3, $add_expr->expressions()->count());

        $this->assertEquals('memory', $add_expr->expressions[0]->type);
        $this->assertEquals(0, $add_expr->expressions[0]->pos);

        $this->assertEquals('mul', $add_expr->expressions[1]->type);
        $this->assertEquals(1, $add_expr->expressions[1]->pos);
        $this->assertEquals('const', $add_expr->expressions[2]->type);
        $this->assertEquals(2, $add_expr->expressions[2]->pos);
    }
}
