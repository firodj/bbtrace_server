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
        $subroutine = Subroutine::with('blocks')->with('blocks.flows')->with('module')->findOrFail($id);

        $result = $subroutine->toArray();
        $subroutine->blocks = $subroutine->blocks->map(function ($block) {
            $block->insn = app(BbAnalyzer::class)->disasmBlock($block);
            return $block;
        });

        return $subroutine;
    }
}
