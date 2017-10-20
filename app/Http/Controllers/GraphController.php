<?php

namespace App\Http\Controllers;

use App\Services\GraphBuilder;
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
        $stops = $request->input('stops');

        $builder = new GraphBuilder();

        return $builder->retrieve($id, $stops);
    }
}
