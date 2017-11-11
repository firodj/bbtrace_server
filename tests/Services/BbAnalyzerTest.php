<?php

use App\Services\BbAnalyzer;
use App\Block;
use PhpAnsiColor\Color;

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
        $block->instructions()->delete();

        // ---
        $block = Block::where('addr', 0x418b70)->first();
        $inst = $this->anal->disasmBlock($block);

        $block = $block->fresh();
        $block->instructions->each(function ($ins) {
            echo Color::set(sprintf("%08x:\t", $ins->addr), 'blue');
            echo Color::set(sprintf("%s\n", $ins->toString()), 'yellow');
        });
    }

    public function testAnalyzeAllBlocks()
    {
        $this->anal->analyzeAllBlocks();
    }

    public function testParseFlowLog()
    {
        $this->anal->parseFlowLog();
    }

    public function testFixOverlappedBlocks()
    {
        $this->anal->fixOverlappedBlocks();
    }

    public function testAssignSubroutines()
    {
        $this->anal->assignSubroutines();
    }
}
