<?php

use App\Services\SubroutineAnalyzer;

use App\Block;

class SubroutineAnalyzerTest extends TestCase
{
    public function testAnalyze()
    {
        // 165 495 167
        $analyzer = new SubroutineAnalyzer(167);
        $analyzer->analyzeDefUse();
        $analyzer->analyzeValue();
    }

}
