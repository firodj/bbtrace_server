<?php

use App\Services\PeParser;

class PeParserTest extends TestCase
{
    public function testMain()
    {
        $fname = env('APP_EXE');

        $pe_parser = new PeParser($fname);

        $pe_parser->parsePe();

        $fout = bbtrace_name($fname, 'pe_parser.dump');

        file_put_contents($fout, serialize($pe_parser));

        $pe_parser2 = unserialize(file_get_contents($fout));

        $this->assertEquals($pe_parser->file_name, $pe_parser2->file_name);

        echo $pe_parser2;
    }

    public function testGetSymbolByVA()
    {
        $fname = env('APP_EXE');

        $pe_parser = new PeParser($fname);

        $pe_parser->parsePe();

        $va = 0x522148;
        $symbol = $pe_parser->getSymbolByVA($va);

        var_dump($symbol);
    }
}
