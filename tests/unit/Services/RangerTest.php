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

    public function testIsTouch()
    {
        $r1 = new Ranger(0, 8);
        $r2 = new Ranger(8, 16);
        $r3 = new Ranger(16, 24);

        $r1->id = 1;
        $r2->id = 2;
        $r3->id = 2;

        $this->assertTrue(Ranger::isTouch($r1, $r2));
        $this->assertTrue(Ranger::isTouch($r2, $r3));
    }

    public function testSubtract()
    {
        $r1 = new Ranger(0, 32);
        $r2 = new Ranger(8, 16);

        $r1->id = 1;
        $r2->id = 2;

        $result = Ranger::subtract($r1, $r2, $minus);

        $this->assertEquals(2, count($result));
        $this->assertEquals([0, 8], [$result[0]->start, $result[0]->end]);
        $this->assertEquals([16, 32], [$result[1]->start, $result[1]->end]);
        $this->assertEquals(1, $result[0]->id);

        $this->assertEquals([8, 16], [$minus->start, $minus->end]);
        $this->assertEquals(2, $minus->id);

        $result = Ranger::subtract($r2, $r1, $minus);
        $this->assertEquals(0, count($result));
        $this->assertEquals([8, 16], [$minus->start, $minus->end]);

        $r3 = new Ranger(16, 24);
        $result = Ranger::subtract($r2, $r3, $minus);

        $this->assertEquals(1, count($result));
        $this->assertEquals($r2, $result[0]);
        $this->assertNull($minus);
    }

    public function testMerge()
    {
        $r1 = new Ranger(0, 8);
        $r2 = new Ranger(8, 16);

        $r1->id = 1;
        $r2->id = 2;

        $result = Ranger::merge([$r1, $r2]);

        $this->assertEquals(1, count($result));
        $this->assertEquals([0, 16], [$result[0]->start, $result[0]->end]);
        $this->assertEquals(2, $result[0]->id);

        $r3 = new Ranger(24, 32);
        $r3->id = 3;

        $result[] = $r3;

        $result2 = Ranger::merge($result);
        $this->assertEquals(2, count($result2));

        $this->assertEquals([24, 32], [$result[1]->start, $result[1]->end]);
        $this->assertEquals(3, $result[1]->id);
    }
}


