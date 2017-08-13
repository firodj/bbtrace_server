<?php

namespace App;

use Serializable;
use Closure;
use Exception;

class TraceLog implements Serializable
{
    private $data;

    public $file_name;
    public $paging;

    private $log_count;

    const PKT_CODE_TRACE = 1;

    public function __construct($file_name)
    {
        $this->paging = null;
        $this->file_name = realpath($file_name);
        $this->initialize();
    }

    public function getLogName(int $log_count)
    {
        return bbtrace_name($this->file_name,
            sprintf("log.%04d", $log_count));
    }

    public function initialize()
    {
        $name = $this->getLogName(1);

        if (!file_exists($name)) {
            throw new Exception("Log file not found: $name log 0001");
        }
    }

    public function serialize(): string
    {
        return serialize([
            'file_name' => $this->file_name,
            'paging' => $this->paging
        ]);
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized);

        $this->file_name = $data['file_name'];
        $this->paging = $data['paging'];
    }

    public function getLogCount()
    {
        if (!isset($this->log_count)) {
            $log_count = 0;
            for (;;$log_count++) {
                $file_name = sprintf("%s.%04d", $this->file_name, $log_count+1);
                if (!is_file($file_name)) break;
            }
            $this->log_count = $log_count;
        }
        return $this->log_count;
    }

    public function buildPaging()
    {
        $this->paging = [];

        for ($log_nbr=1; $log_nbr <= $this->getLogCount(); $log_nbr++) {
            $file_name = sprintf("%s.%04d", $this->name, $log_nbr);
            $fp = fopen($file_name, 'rb');

            while (!feof($fp)) {
                $pos = ftell($fp);
                $data = fread($fp, (4+8+4));
                if (!feof($fp)) {
                    $data = unpack('Lcode/Qts/Lthread', $data);
                } else break;

                if ($data['code'] == self::PKT_CODE_TRACE) {

                    $this->paging[] = [$log_nbr, $pos];

                    $data = unpack('Lsize', fread($fp, 4));
                    fseek($fp, $data['size']*4, SEEK_CUR);
                }
            }

            fclose($fp);
        }
    }

    /*
     * parseLog using `foreach`
     */
    public function parseLog($pkt_start = 0, $pkt_stop = null)
    {
        if (! isset($this->paging)) $this->buildPaging();

        if (is_null($pkt_stop)) $pkt_stop = count($this->paging);

        $last_file_no = null;
        $fp = null;

        try {
            for ($pkt_no = $pkt_start; $pkt_no < $pkt_stop; $pkt_no++) {
                list($file_no, $file_offset) = $this->paging[ $pkt_no ];

                if ($file_no != $last_file_no) {
                    if ($fp) fclose($fp);

                    $file_name = sprintf("%s.%04d", $this->name, $file_no);
                    $fp = fopen($file_name, 'rb');
                    $last_file_no = $file_no;

                    fprintf(STDERR, "Open: %s\n", $file_name);
                }

                fseek($fp, $file_offset, SEEK_SET);
                if (feof($fp)) {
                    fprintf(STDERR, "Invalid seekf offset: %d\n", $file_offset);
                    break;
                }

                $data = fread($fp, (4+8+4));
                if (feof($fp)) {
                    fprintf(STDERR, "Invalid header on: %d\n", $file_offset);
                    break;
                }

                $header = (object)unpack('Lcode/Qts/Lthread', $data);

                if ($header->code == self::PKT_CODE_TRACE) {
                    $header->size = unpack('L', fread($fp, 4))[1];
                    $header->pkt_no = $pkt_no;

                    yield $pkt_no => (object)['header' => $header, 'raw_data' => fread($fp, $header->size*4)];
                }

            }
        } finally {
            if ($fp) fclose($fp);
        }
    }

    public function __toString()
    {
        $output = sprintf("Name: %s\n", $this->name);
        $output.= sprintf("File Count: %d\n", $this->getLogCount());
        $output.= sprintf("paging: %d\n", count($this->paging));

        return $output;
    }

}
