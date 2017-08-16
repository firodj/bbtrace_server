<?php

use App\BbAnalyzer;

class BbAnalyzerTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->anal = new BbAnalyzer(env('APP_EXE'));
    }

    public function testParseInfo()
    {
        $this->anal->parseInfo();
    }

    public function testParseFunc()
    {
        $this->anal->parseFunc();
    }

    public function testDisasmBlock()
    {
        $block = $this->anal->getStartBlock();

        $inst = $this->anal->disasmBlock($block);

        $this->assertEquals(1, count($inst));
    }

    public function testAnalyzeAllBlocks()
    {
        $this->anal->analyzeAllBlocks();
    }

    public function testAssignSubroutines()
    {
        $this->anal->assignSubroutines();
    }

    public function testBuildIngress()
    {
        $this->anal->loadAll();
        $states = $this->anal->prepareStates();
        foreach ($this->anal->trace_log->parseLog() as $pkt_no => $chunk) {
            //$this->anal->storeStates($pkt_no, $states);
            $states = $this->anal->buildIngress($chunk, $states);
            $this->anal->storeFlows($states);
        }
    }
}
