<?php

use App\Services\SubroutineAnalyzer;

class SubroutineAnalyzerTest extends TestCase
{
    public function testAnalyzeStack()
    {
        $analyzer = new SubroutineAnalyzer();
        $analyzer->analyze(0x426d1d);
    }
}
