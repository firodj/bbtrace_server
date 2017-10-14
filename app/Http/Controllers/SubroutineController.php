<?php

namespace App\Http\Controllers;

use App\Services\BbAnalyzer;
use App\Subroutine;
use App\Reference;
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
            $insn = app(BbAnalyzer::class)->disasmBlock($block);

            foreach($insn as &$ins) {
                $notes = [];
                foreach($ins->detail->x86->operands as $opnd) {
                    $addr = null;
                    if ($opnd->type == 'imm') {
                        $addr = $opnd->imm;
                    }
                    if ($opnd->type == 'mem') {
                        if ($opnd->mem->base == 0 &&
                            $opnd->mem->index == 0 &&
                            $opnd->mem->segment == 0 &&
                            $opnd->mem->scale == 1) {
                            $addr = $opnd->mem->disp;
                        }
                    }

                    if ($addr) {
                        $subroutine = Subroutine::find($addr);
                        if ($subroutine) {
                            $notes[] = $subroutine->name;
                        }
                        $symbol = app(BbAnalyzer::class)->pe_parser->getSymbolByVA($addr);
                        if ($symbol) {
                            $notes[] = sprintf("%s!%s", $symbol[0], $symbol[1]);
                        }
                        $reference = Reference::where(['id' => $addr,
                            'ref_addr' => $ins->address])->first();
                        if ($reference) {
                            $notes[] = [
                                'D' => 'const',
                                'V' => 'var',
                                'C' => 'code',
                                'X' => 'unknown',
                            ][$reference->kind];
                        }
                    }
                }
                if (!empty($notes)) {
                    $ins->notes = '; ' . implode(', ', $notes);
                }
            }

            $block->insn = $insn;
            return $block;
        });

        return $subroutine;
    }
}
