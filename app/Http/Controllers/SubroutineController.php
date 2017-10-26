<?php

namespace App\Http\Controllers;

use App\Services\BbAnalyzer;
use App\Symbol;
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
        $subroutine = Subroutine::with('blocks')->with('module')->find($id);

        if (! $subroutine) {
            $symbol = Symbol::with('module')->find($id);
            if ($symbol) {
                $result = $symbol->toArray();
                $result['blocks'] = [];
                $result['links'] = [];
                return $result;
            }

            return [
                'id' => 0,
                'name' => '',
                'blocks' => [],
                'links' => [],
            ];
        }

        $result = $subroutine->toArray();
        $links = [];
        $aliens = [];

        $result['blocks'] = $subroutine->blocks->map(function ($block) use (&$aliens, &$links) {
            // mark this block is not alien
            $aliens[ $block->id ] = false;

            // Form flow
            foreach($block->nextFlows as $flow) {
                $key = sprintf("%s-%s", $block->id, $flow->id);
                $links[$key] = [
                    'source_id' => $block->id,
                    'target_id' => $flow->id,
                    'key' => $key,
                ];
                if (! array_key_exists($flow->id, $aliens)) {
                    $aliens[ $flow->id ] = true;
                }
                if ($block->jump_mnemonic == 'call') {
                    $key = sprintf("%s-%s", $flow->id, $block->end);
                    $links[$key] = [
                        'source_id' => $flow->id,
                        'target_id' => $block->end,
                        'key' => $key,
                    ];
                    if (! array_key_exists($block->end, $aliens)) {
                        $aliens[ $block->end ] = true;
                    }
                }
            }

            // Form instruction
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
            $block->type = 'block';

            return $block;
        });

        $result['links'] = array_values($links);

        foreach($aliens as $id => $value) {
            if (! $value) continue;

            $alien = [
                'id' => $id,
                'type' => 'unknown'
            ];

            $result['blocks'][] = $alien;
        }

        return $result;
    }
}
