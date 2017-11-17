<?php

use App\Services\Ranger;

class RangerTest extends TestCase
{
    public function testIsOverlap()
    {
        $r1 = new Ranger(0, 8);
        $r2 = new Ranger(8, 16);
        $r3 = new Ranger(0, 32);

        $this->assertTrue(Ranger::isOverlap($r1, $r3));
        $this->assertFalse(Ranger::isOverlap($r1, $r2));
    }

    public function testSubtract()
    {
        $r1 = new Ranger(0, 32);
        $r2 = new Ranger(8, 16);

        $result = Ranger::subtract($r1, $r2);

        $this->assertEquals(2, count($result));
        $this->assertEquals([0, 8], [$result[0]->start, $result[0]->end]);
        $this->assertEquals([16, 32], [$result[1]->start, $result[1]->end]);

        $result = Ranger::subtract($r2, $r1);
        $this->assertEquals(0, count($result));

        $r3 = new Ranger(16, 24);
        $result = Ranger::subtract($r2, $r3);

        $this->assertEquals(1, count($result));
        $this->assertEquals($r2, $result[0]);
    }

    public function testMerge()
    {
        $r1 = new Ranger(0, 8);
        $r2 = new Ranger(8, 16);
        $result = Ranger::merge([$r1, $r2]);

        $this->assertEquals(1, count($result));
        $this->assertEquals([0, 16], [$result[0]->start, $result[0]->end]);

        $r3 = new Ranger(24, 32);
        $result[] = $r3;

        $result2 = Ranger::merge($result);
        $this->assertEquals(2, count($result2));

        $this->assertEquals([24, 32], [$result[1]->start, $result[1]->end]);
    }
}


