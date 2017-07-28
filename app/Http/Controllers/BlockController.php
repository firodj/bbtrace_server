<?php

namespace App\Http\Controllers;

use App\BbAnalyzer;
use Illuminate\Http\Request;
use Log;

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

        $keys = array_keys($bb_analyzer->getTraceLog()->blocks);

        return array_map(function($block_id) use ($bb_analyzer) {
            $block = $bb_analyzer->getTraceLog()->blocks[$block_id];
            return (object)[
                'id' => $block_id,
            ];
        }, array_slice($keys, 0, 20));
    }

    public function show(Request $request, $id)
    {
        $bb_analyzer = app(BbAnalyzer::class);

        return $bb_analyzer->getBlock($id);
    }
}
