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
            $bb_analyzer = new BbAnalyzer(env('APP_EXE'));
            return $bb_analyzer;
        });
    }
}
