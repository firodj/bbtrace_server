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
                $result['end'] = 0;
                $result['blocks'] = [];
                $result['links'] = [];
                return $result;
            }

            return [
                'id' => 0,
                'addr' => 0,
                'end' => 0,
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

            $aliens[ make_key($block) ] = false;

            // Form flow
            foreach($block->nextFlows as $flow) {
                if ($block->jump_mnemonic == 'ret') {
                    continue;
                }
                $key = sprintf("%s-%s", make_key($block), make_key($flow->block));
                $links[$key] = [
                    'source_id' => make_key($block),
                    'target_id' => make_key($flow->block),
                    'key' => $key,
                ];
                if (! array_key_exists( make_key($flow->block), $aliens)) {
                    $aliens[ make_key($flow->block) ] = true;
                }
                if ($block->jump_mnemonic == 'call') {
                    $end_block = Block::where('addr', $block->end)->first();
                    if ($end_block) {
                        $key = sprintf("%s-%s", make_key($block), make_key($end_block));
                        $links[$key] = [
                            'source_id' => make_key($block),
                            'target_id' => make_key($end_block),
                            'key' => $key,
                        ];
                        if (! array_key_exists(make_key($end_block), $aliens)) {
                            $aliens[ make_key($end_block) ] = true;
                        }
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

            $result = $block->toArray();

            $result['type'] = 'block';
            $result['id'] = make_key($block);

            return $result;
        });

        $result['links'] = array_values($links);

        foreach($aliens as $id => $value) {
            if (! $value) continue;

            $alien = [
                'id' => $id,
                'type' => 'unknown'
            ];

            $keyz = explode('_', $id);

            if ($keyz[0] == 'subroutines') {
                $subroutine = Subroutine::find($keyz[1]);
                if ($subroutine) {
                    $alien = [
                        'id' => $id,
                        'addr' => $subroutine->addr,
                        'type' => 'subroutine',
                        'name' => $subroutine->name,
                    ];
                }
            }
            else if ($keyz[0] == 'symbols') {
                $symbol = Symbol::with('module')->find($keyz[1]);
                if ($symbol) {
                    $alien = [
                        'id' => $id,
                        'addr' => $symbol->addr,
                        'type' => 'symbol',
                        'name' => $symbol->getDisplayName()
                    ];
                }
            }
            else if ($keyz[0] == 'blocks') {
                $block = Block::with('subroutine')->find($keyz[1]);
                if ($block) {
                    $alien = [
                        'id' => $id,
                        'addr' => $block->addr,
                        'type' => 'other',
                        'name' => $block->subroutine->name
                    ];
                }
            }

            $result['blocks'][] = $alien;
        }

        return $result;
    }
}
