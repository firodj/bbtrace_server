<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use App\Block;

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
        return Block::paginate();
    }

    public function show(Request $request, $id)
    {
        return Block::findOrFail($id);
    }
}
