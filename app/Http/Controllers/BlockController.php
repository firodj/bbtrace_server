<?php

namespace App\Http\Controllers;

use App\BbAnalyzer;

class BlockController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function index()
    {
        $bb_analyzer = app(BbAnalyzer::class);

        return array_keys($bb_analyzer->getTraceLog()->blocks);
    }
}
