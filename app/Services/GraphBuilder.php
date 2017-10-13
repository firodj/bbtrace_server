<?php

namespace App\Services;

use App\Subroutine;
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

    public function retrieve($node_id = null, $stops = 3)
    {
        $first_node = is_null($node_id) ? GraphNode::first() : GraphNode::findOrFail((int)$node_id);

        $pendings = [$first_node];

        $nodes = [];
        $links = [];

        while ($node = array_shift($pendings)) {
            $nodes[] = array_merge($node->toArray(), [
                'stopped' => $stops == 0 && $node->links->count() > 0,
            ]);

            if ($stops == 0) continue;

            foreach($node->links as $link) {
                $links[] = array_merge($link->toArray(), [
                    'source' => $link->source_id, 'target' => $link->target_id,
                ]);
                $pendings[] = $link->target;
            }

            $stops--;
        }

        return [
            'nodes' => $nodes,
            'links' => $links,
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
        return array_key_exists($pending->item->id, $this->visits);
    }

    public function markVisit($pending)
    {
        $this->visits[$pending->item->id] = $pending;
    }

    public function buildSubroutine($pending)
    {
        $isCopy = $this->isCopy($pending);

        $subroutine = $pending->item;

        $node = new GraphNode();
        $node->subroutine_id = $subroutine->id;
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
                // skip on ret, need is_bidi.
                if ($block->jump_mnemonic == 'ret') {
                    if ($next = $flow->block) {
                        if ($next->id != $next->subroutine_id) continue;
                    } else {
                        continue;
                    }
                }

                if ($next = $flow->block) {
                    if ($next->subroutine_id == $subroutine->id) continue;
                    if (array_key_exists($next->id, $targets)) continue;

                    $this->pendings[] = (object)[
                        'item' => $next->subroutine,
                        'xref' => $flow->xref,
                        'last' => $node->id,
                    ];

                    $targets[ $next->id ] = true;
                }

                if ($next = $flow->symbol) {
                    if (array_key_exists($next->id, $targets)) continue;

                    $this->pendings[] = (object)[
                        'item' => $next,
                        'xref' => $flow->xref,
                        'last' => $node->id
                    ];

                    $targets[ $next->id ] = true;
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
            if ($next = $flow->block) {
                if ($next->subroutine_id != $next->id) continue;

                $this->pendings[] = (object)[
                    'item' => $next->subroutine,
                    'xref' => $flow->xref,
                    'last' => $node->id
                ];
            }
            if ($next = $flow->symbol) {
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
