<?php

namespace App;

class BbAnalyzer
{
    public function open($fname) {
        $data = unserialize(file_get_contents($fname));
        if ($data instanceof PeParser) {
            $this->pe_parser = $data;
            return $data;
        }
        if ($data instanceof TraceLog) {
            $this->trace_log = $data;
            return $data;
        }
    }

    public function experiment()
    {
        for ($i=1; $i<=$this->trace_log->getLogCount(); $i++) {
            $this->trace_log->parseLog($i, 0,
                function($header, $raw_data) {
                fprintf(STDERR, "%d.", $header['pkt_no']);

                $data = unpack('V*', $raw_data);

                foreach($data as $block_id) {
                    if (isset($this->trace_log->functions[$block_id])) {
                        $func = $this->trace_log->functions[$block_id];
                        echo $func['function_name'].PHP_EOL;
                    }
                    if (isset($this->trace_log->blocks[$block_id])) {
                        $block = $this->trace_log->blocks[$block_id];
                        echo "\t".dechex($block_id).PHP_EOL;
                    } else if (isset($trace_log->symbols[$block_id])) {
                        $sym = $this->trace_log->symbols[$block_id];
                        echo "\t\t".dechex($block_id)." ".$sym['symbol_name'].PHP_EOL;
                    } else {
                        echo sprintf("Unknown: 0x%08x\n", $block_id);
                    }
                }

                return;
            });
        }

    }
}
