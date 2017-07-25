<?php

class PeParserTest extends TestCase
{
    public function testMain()
    {
        $fname = base_path('../logs/psxfin.exe');

        $pe_parser = new App\PeParser($fname);

        $pe_parser->parsePe();

        $fout = base_path('../logs/psxfin.pe_parser.dump');
        file_put_contents($fout, serialize($pe_parser));

        $pe_parser2 = (new App\BbAnalyzer)->open($fout);

        echo $pe_parser2;
    }
}
