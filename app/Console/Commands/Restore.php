<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class Restore extends Command
{
    protected $signature = 'db:restore';
    protected $description = 'Restore Databse';

    public function handle()
    {
        $db = config('database.connections')[config('database.default')];

        $dumpFile = storage_path(sprintf('dumps/%s.sql',  $db['database']));
        if (!file_exists($dumpFile)) {
            $this->error("Dump file: $dumpFile is not found!");
            return;
        }

        $clientCommandPath = env('MYSQL_CLIENT_PATH');

        $command = sprintf('%smysql --user=%s --password=%s --host=%s --port=%s %s < %s',
            $clientCommandPath,
            escapeshellarg($db['username']),
            escapeshellarg($db['password']),
            escapeshellarg($db['host']),
            escapeshellarg($db['port']),
            escapeshellarg($db['database']),
            escapeshellarg($dumpFile)
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
