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

        $pe_parser2 = unserialize(file_get_contents($fout));

        $this->assertEquals($pe_parser->file_name, $pe_parser2->file_name);

        echo $pe_parser2;
    }
}
