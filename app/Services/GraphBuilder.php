<?php

namespace App\Services;

use App\Subroutine;
use App\Block;
use App\Symbol;
use App\GraphNode;
use App\GraphLink;

class GraphBuilder
{
    public $visits;
    public $pendings;

    public function __construct()
    {
    }

    public function retrieve($node_id = null, $stops = null)
    {
        if (!isset($stops)) $stops = 2;
        $first_node = $node_id ? GraphNode::findOrFail((int)$node_id) : GraphNode::first();

        $levels = [ [$first_node] ];

        $nodes = [];
        $links = [];

        // getPrevious
        foreach ($first_node->copies()->get() as $node) {
            foreach ($node->prevLinks as $link) {
                $links[] = $link->toArray();

                if ($link->source_id != $first_node->id) { // Skip recursive
                    $nodes[$link->source->id] = array_merge(
                        $link->source->toArray(), [
                            'has_more' => true
                        ]);
                }

                if ($link->target_id != $first_node->id) { // Skip recursive
                    $nodes[$link->target->id] = array_merge(
                        $link->target->toArray(), [
                            'has_more' => $link->target->links->count() > 0
                        ]);
                }
            }
        }

        while ($pendings = array_shift($levels)) {
            $nextPendings = [];
            while ($node = array_shift($pendings)) {
                if (array_key_exists($node->id, $nodes)) continue;

                $nodes[$node->id] = array_merge(
                    $node->toArray(),
                    ['has_more' => false]
                );

                unset($nodes[$node->id]['copies']);

                if ($stops <= 0 && !$node->is_symbol) {
                    $nodes[$node->id]['has_more'] = $node->links->count() > 0;
                    continue;
                }

                foreach($node->links as $link) {
                    $links[] = $link->toArray();
                    $nextPendings[] = $link->target;
                }
            }

            if (!empty($nextPendings)) $levels[] = $nextPendings;

            $stops--;
        }

        return [
            'nodes' => array_values($nodes),
            'links' => $links,
            'subroutine_id' => $first_node->subroutine_type == 'subroutines' ? $first_node->subroutine_id : 0,
        ];
    }

    public function truncate()
    {
        app('db')->table(with(new GraphNode)->getTable())->truncate();
        app('db')->table(with(new GraphLink)->getTable())->truncate();
    }

    public function build()
    {
        $this->truncate();

        $block = app(BbAnalyzer::class)->getStartBlock();

        $this->visits = [];
        $this->pendings = [
            (object)[
                'item' => $block->subroutine,
                'last' => null,
            ]
        ];

        while ($pending = array_shift($this->pendings)) {
            if ($pending->item instanceof Subroutine) {
                $this->buildSubroutine($pending);
            }
            if ($pending->item instanceof Symbol) {
                $this->buildSymbol($pending);
            }
        }
    }

    public function isCopy($pending)
    {
        return array_key_exists($pending->item->addr, $this->visits);
    }

    public function markVisit($pending)
    {
        $this->visits[$pending->item->addr] = $pending;
    }

    public function buildSubroutine($pending)
    {
        $isCopy = $this->isCopy($pending);

        $subroutine = $pending->item;

        $node = new GraphNode();
        $node->subroutine_id = $subroutine->id;
        $node->subroutine_type = $subroutine->getTable();
        $node->label = $subroutine->name;
        $node->is_copy = $isCopy;
        $node->is_symbol = false;
        $node->save();

        if ($pending->last) {
            $link = new GraphLink();
            $link->source_id = $pending->last;
            $link->target_id = $node->id;
            $link->xref = $pending->xref;
            $link->save();
        }

        if ($isCopy) return;
        $this->markVisit($pending);

        $targets = [];

        foreach($subroutine->blocks as $block) {
            foreach($block->nextFlows as $flow) {
                $next = $flow->block;

                // skip on ret, need is_bidi.
                if ($block->jump_mnemonic == 'ret') {
                    if ($next instanceof Block) {
                        // return to (...) and next block is start of function
                        if ($next->addr != $next->subroutine->addr) continue;
                    } else {
                        continue;
                    }
                }

                if ($next instanceof Block) {
                    if ($next->subroutine_id == $subroutine->id) continue;
                    if (array_key_exists($next->addr, $targets)) continue;

                    $this->pendings[] = (object)[
                        'item' => $next->subroutine,
                        'xref' => $flow->xref,
                        'last' => $node->id,
                    ];

                    $targets[ $next->addr ] = true;
                } else if ($next instanceof Symbol) {
                    if (array_key_exists($next->addr, $targets)) continue;

                    $this->pendings[] = (object)[
                        'item' => $next,
                        'xref' => $flow->xref,
                        'last' => $node->id
                    ];

                    $targets[ $next->addr ] = true;
                }
            }
        }

        fprintf(STDERR, "Subroutine #{$node->id} {$node->label}\n");
    }

    protected function buildSymbol($pending)
    {
        $isCopy = $this->isCopy($pending);

        $symbol = $pending->item;

        $node = new GraphNode();
        $node->subroutine_id = $symbol->id;
        $node->subroutine_type = $symbol->getTable();
        $node->label = $symbol->getDisplayName();
        $node->is_copy = $isCopy;
        $node->is_symbol = true;
        $node->save();

        if ($pending->last) {
            $link = new GraphLink();
            $link->source_id = $pending->last;
            $link->target_id = $node->id;
            $link->xref = $pending->xref;
            $link->save();
        }

        if ($isCopy) return;
        $this->markVisit($pending);

        foreach($symbol->nextFlows as $flow) {
            $next = $flow->block;
            if ($next instanceof Block) {
                if ($next->subroutine->addr != $next->addr) continue;

                $this->pendings[] = (object)[
                    'item' => $next->subroutine,
                    'xref' => $flow->xref,
                    'last' => $node->id
                ];
            } else if ($next instanceof Symbol) {
                $this->pendings[] = (object)[
                    'item' => $next,
                    'xref' => $flow->xref,
                    'last' => $node->id
                ];
            }
        }

        fprintf(STDERR, "Symbol #{$node->id} {$node->label}\n");
    }
}
