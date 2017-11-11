<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\BbAnalyzer;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Block;
use App\Symbol;
use App\Subroutine;
use App\Instruction;
use App\Observers;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Relation::morphMap([
            'symbols' => Symbol::class,
            'blocks' => Block::class,
            'subroutines' => Subroutine::class,
        ]);

        Instruction::observe(Observers\Instruction::class);
    }

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
