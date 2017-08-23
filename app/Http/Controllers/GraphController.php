<?php

namespace App\Http\Controllers;

use App\BbAnalyzer;
use App\GraphBuilder;
use Illuminate\Http\Request;
use App\Block;
use Log;

class GraphController extends Controller
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
        $id = $request->input('id');
        $block = null;
        if ($id) {
            $id_ = explode('#', $id);
            $block_id = hexdec($id_[0]);
            $block = Block::findOrFail($block_id);
        }

        $builder = new GraphBuilder($block);

        $builder->build(3);

        return $builder->data();
    }
}
