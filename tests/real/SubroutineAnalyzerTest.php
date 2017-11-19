<?php

use App\Services\SubroutineAnalyzer;

use App\Block;

class SubroutineAnalyzerTest extends TestCase
{
    public function testAnalyze()
    {
        $analyzer = new SubroutineAnalyzer(497);
        foreach ($analyzer->eachBlock() as $block => $state) {
            $analyzer->blockDefUse($block, $state);
        }
    }
}
