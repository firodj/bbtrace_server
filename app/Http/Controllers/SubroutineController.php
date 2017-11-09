<?php

namespace App\Http\Controllers;

use App\Services\BbAnalyzer;
use App\Services\SubroutineAnalyzer;
use App\Symbol;
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
        $analyzer = new SubroutineAnalyzer();

        $result = $analyzer->graph($id);

        if (! $result) {
            $symbol = Symbol::with('module')->find($id);
            if ($symbol) {
                $result = $symbol->toArray();
                $result['end'] = 0;
                $result['blocks'] = [];
                $result['links'] = [];
                return $result;
            }
            $result = [
                'id' => 0,
                'addr' => 0,
                'end' => 0,
                'name' => '',
                'blocks' => [],
                'links' => [],
            ];
        }

        return $result;
    }
}
