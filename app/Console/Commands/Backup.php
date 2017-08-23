<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class Backup extends Command
{
    protected $signature = 'db:backup';
    protected $description = 'Backup Databse';

    public function handle()
    {
        $db = config('database.connections')[config('database.default')];

        $store = storage_path('dumps');
        if (!file_exists($store)) {
            dump($store);
            mkdir($store, 0700, true);
        }

        $destinationFile = sprintf('%s/%s.sql', $store, $db['database']);
        $dumpCommandPath = env('MYSQL_DUMP_PATH');

        $command = sprintf('%smysqldump --user=%s --password=%s --host=%s --port=%s %s > %s',
            $dumpCommandPath,
            escapeshellarg($db['username']),
            escapeshellarg($db['password']),
            escapeshellarg($db['host']),
            escapeshellarg($db['port']),
            escapeshellarg($db['database']),
            escapeshellarg($destinationFile)
        );

        dump($command);

        $process = new Process($command);
        $process->setTimeout(999999999);
        $process->run();
        if ($process->isSuccessful())
        {
            return true;
        }

        dump($process->getErrorOutput());
    }
}
