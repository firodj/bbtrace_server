<?php

use App\Services\SubroutineAnalyzer;

use App\Block;

class SubroutineAnalyzerTest extends TestCase
{
    public function testAnalyze165()
    {
        $analyzer = new SubroutineAnalyzer(165);
        $analyzer->analyzeDefUse();
        $analyzer->analyzeValue();
    }

    public function testAnalyze495()
    {
        $analyzer = new SubroutineAnalyzer(495);
        $analyzer->analyzeDefUse();
        $analyzer->analyzeValue();
    }

}
