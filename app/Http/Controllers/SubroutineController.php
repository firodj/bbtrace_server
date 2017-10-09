<?php

namespace App\Http\Controllers;

use App\BbAnalyzer;
use App\Subroutine;
use Illuminate\Http\Request;
use Log;

class SubroutineController extends Controller
{
    public function __construct()
    {
    }

    public function index(Request $request)
    {
        return Subroutine::has('blocks')->paginate(100);
    }

    public function show(Request $request, $id)
    {
        return Subroutine::with('blocks')->with('module')->findOrFail($id);
    }
}
