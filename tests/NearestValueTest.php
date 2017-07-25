<?php

class NearestValueTest extends TestCase
{
    public function testNearestDefault()
    {
        $array  = [5000,3000,1000,2000,4000];
        sort($array, SORT_NUMERIC);

        $this->assertEquals(1000,
            App\NearestValue::array_numeric_sorted_nearest($array, 1337)
        );

        $this->assertEquals(2000,
            App\NearestValue::array_numeric_sorted_nearest($array, 1773)
        );
    }

    public function testNearestLower()
    {
        $array  = [5000,3000,1000,2000,4000];
        sort($array, SORT_NUMERIC);

        $this->assertEquals(1000,
            App\NearestValue::array_numeric_sorted_nearest($array, 1337,
                App\NearestValue::ARRAY_NEAREST_LOWER)
        );

        $this->assertEquals(1000,
            App\NearestValue::array_numeric_sorted_nearest($array, 1773,
                App\NearestValue::ARRAY_NEAREST_LOWER)
        );
    }

    public function testNearestHigher()
    {
        $array  = [5000,3000,1000,2000,4000];
        sort($array, SORT_NUMERIC);

        $this->assertEquals(2000,
            App\NearestValue::array_numeric_sorted_nearest($array, 1337,
                App\NearestValue::ARRAY_NEAREST_HIGHER)
        );

        $this->assertEquals(2000,
            App\NearestValue::array_numeric_sorted_nearest($array, 1773,
                App\NearestValue::ARRAY_NEAREST_HIGHER)
        );
    }
}
