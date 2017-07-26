<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\BbAnalyzer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(BbAnalyzer::class, function($app)
        {
            $bb_analyzer = new BbAnalyzer();

            // FIXME: use configurable value
            $bb_analyzer->open(base_path('../logs/psxfin.trace_log.dump'));
            $bb_analyzer->open(base_path('../logs/psxfin.pe_parser.dump'));

            return $bb_analyzer;
        });
    }
}
