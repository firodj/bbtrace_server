<?php

namespace App\Http\Controllers;

use App\Services\BbAnalyzer;
use App\Symbol;
use App\Block;
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
                    $key = sprintf("%s-%s", $block->id, $block->end);
                    $links[$key] = [
                        'source_id' => $block->id,
                        'target_id' => $block->end,
                        'key' => $key,
                    ];
                    if (! array_key_exists($block->end, $aliens)) {
                        $aliens[ $block->end ] = true;
                    }
                }
            }

            // If no codes disasm
            if (! $block->codes ) {
                $codes = [];
                $insn = app(BbAnalyzer::class)->disasmBlock($block);
                foreach($insn as &$ins) {
                    $codes[] = [
                        'code' => sprintf("%s %s", $ins->mnemonic, $ins->op_str)
                    ];
                }
                $block->codes = $codes;
            }

            $block->type = 'block';

            return $block;
        });

        $result['links'] = array_values($links);

        foreach($aliens as $id => $value) {
            if (! $value) continue;

            $subroutine = Subroutine::find($id);
            $alien = [
                'id' => $id,
                'type' => 'unknown'
            ];

            if ($subroutine) {
                $alien = [
                    'id' => $id,
                    'type' => 'subroutine',
                    'name' => $subroutine->name,
                ];
            } else {
                $symbol = Symbol::with('module')->find($id);
                if ($symbol) {
                    $alien = [
                        'id' => $id,
                        'type' => 'symbol',
                        'name' => $symbol->getDisplayName()
                    ];
                } else {
                    $block = Block::with('subroutine')->find($id);
                    if ($block) {
                        $alien = [
                            'id' => $id,
                            'type' => 'other',
                            'name' => $block->subroutine->name
                        ];
                    }
                }
            }

            $result['blocks'][] = $alien;
        }

        return $result;
    }
}
