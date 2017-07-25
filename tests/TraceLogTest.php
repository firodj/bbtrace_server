<?php

class TraceLogTest extends TestCase
{
    public function testMain()
    {
        $fname = base_path('../logs/bbtrace.psxfin.exe.log.info');

        $trace_log = new App\TraceLog($fname);
        $trace_log->parseInfo();
        $trace_log->parseFunc();

        $fout = base_path('../logs/psxfin.trace_log.dump');
        file_put_contents($fout, serialize($trace_log));

        $trace_log2 = (new App\BbAnalyzer)->open($fout);

        echo $trace_log2;
    }
}
