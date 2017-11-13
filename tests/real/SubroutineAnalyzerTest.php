<?php

use App\Services\SubroutineAnalyzer;

use App\Block;

class SubroutineAnalyzerTest extends TestCase
{
    public function testAnalyzeStack()
    {
        $analyzer = new SubroutineAnalyzer();
        $analyzer->analyze(0x426d1d);
    }

    public function testExgen()
    {
        $analyzer = new SubroutineAnalyzer();
        $analyzer->exgen(120);
    }
}
