<?php

use App\BbAnalyzer;

class BbAnalyzerTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $anal = new BbAnalyzer(env('APP_EXE'));
        
    }
}
