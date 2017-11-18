<?php

use App\Services\SubroutineAnalyzer;

use App\Block;

class SubroutineAnalyzerTest extends TestCase
{
    public function testAnalyze()
    {
        $analyzer = new SubroutineAnalyzer();
        $analyzer->analyze2(497);
    }

    public function testExgen()
    {
        $analyzer = new SubroutineAnalyzer();
        $analyzer->exgen(120);
    }
}
