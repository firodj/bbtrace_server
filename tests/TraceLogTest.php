<?php

class TraceLogTest extends TestCase
{
    public function testBuildPagingAndRestore()
    {
        $fname = base_path('../logs/psxfin.exe');

        $trace_log = new App\TraceLog($fname);

        $trace_log->buildPaging();

        $fout = base_path('../logs/bbtrace.psxfin.exe.trace_log.dump');
        file_put_contents($fout, serialize($trace_log));

        $trace_log2 = unserialize(file_get_contents($fout));

        fprintf(STDERR, $trace_log2);
        $this->assertEquals(420, count($trace_log2->paging));
        $this->assertContains("bbtrace.psxfin.exe.log", $trace_log2->name);

        $entry_point = 0x41e4cf;

        foreach ($trace_log2->parseLog() as $pkt_no => $chunk)
        {
            fprintf(STDERR, var_export($chunk->header, true));

            $data = unpack('V*', $chunk->raw_data);
            $this->assertEquals($chunk->header->size, count($data));

            $this->assertEquals($entry_point, $data[1]);

            break;
        };
    }
}
