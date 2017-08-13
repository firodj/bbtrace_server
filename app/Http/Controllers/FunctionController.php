<?php

namespace App\Http\Controllers;

use App\BbAnalyzer;
use Illuminate\Http\Request;
use Log;

class FunctionController extends Controller
{
    public function __construct()
    {
    }

    public function index(Request $request)
    {
        $bb_analyzer = app(BbAnalyzer::class);

        $keys = array_keys($bb_analyzer->function_blocks);

        $limit = 20;
        $activeStep = (int) $request->input('activeStep', 0);
        $steps = ceil(count($keys) / $limit);
        if ($activeStep < 0) $activeStep = 0;
        if ($activeStep >= $steps) $activeStep = $steps-1;

        $functions = array_map(function($function_id) use ($bb_analyzer) {
            return $bb_analyzer->getFunction($function_id);
        }, array_slice($keys,
            $activeStep * $limit, $limit)
        );

        return [
            'items' => $functions,
            'activeStep' => $activeStep,
            'steps' => $steps,
        ];
    }

    public function show(Request $request, $id)
    {
        $bb_analyzer = app(BbAnalyzer::class);

        return $bb_analyzer->getFunction($id);
    }
}
