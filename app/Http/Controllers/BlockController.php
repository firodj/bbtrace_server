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

    public function index(Request $request)
    {
        $bb_analyzer = app(BbAnalyzer::class);

        $keys = array_keys($bb_analyzer->getTraceLog()->blocks);

        $limit = $request->input('limit', 20);
        $offset = (int) $request->input('offset', 0);
        if ($offset < 0) $offset = 0;

        $bb_analyzer = app(BbAnalyzer::class);

        $blocks = array_map(function($block_id) use ($bb_analyzer) {
            return $bb_analyzer->getBlock($block_id);
        }, array_slice($keys,
            $offset, $limit + 1)
        );

        $hasMore = count($blocks) > $limit;
        if ($hasMore) array_pop($blocks);

        $hasPrev = $offset > 0;

        return ['blocks' => $blocks, 'hasMore' => $hasMore, 'hasPrev' => $hasPrev, 'offset' => $offset];
    }

    public function show(Request $request, $id)
    {
        $bb_analyzer = app(BbAnalyzer::class);

        return $bb_analyzer->getBlock($id);
    }
}
