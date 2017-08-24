<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\GraphBuilder;

class Graph extends Command
{
    protected $signature = 'graph';
    protected $description = 'Graph Builder';

    private $anal;

    public function handle()
    {
        $builder = new GraphBuilder();

        $builder->build();
    }
}

