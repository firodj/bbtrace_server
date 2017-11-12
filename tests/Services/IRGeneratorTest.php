i<?php

use App\Services\IRGenerator;
use App\Expression;

class IRGeneratorTest extends TestCase
{
    public function testCreateRegExpression()
    {
        $service = new IRGenerator();

        $expr = $service->createRegExpression('eax');

        $this->assertEquals('memory', $expr->type);
        $this->assertEquals(1002, $expr->domain);
    }

    public function testCreateConstExpression()
    {
        $service = new IRGenerator();

        $expr = $service->createConstExpression(-1, 32);

        $this->assertEquals('const', $expr->type);
        $this->assertEquals(-1, $expr->const);
    }
}
