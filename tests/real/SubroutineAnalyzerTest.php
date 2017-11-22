<?php

use App\Services\SubroutineAnalyzer;

use App\Block;

class SubroutineAnalyzerTest extends TestCase
{
    public function testAnalyze()
    {
        $analyzer = new SubroutineAnalyzer(495); // 165
        $analyzer->analyzeDefUse();
    }
}
