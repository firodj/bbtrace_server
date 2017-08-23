<?php

namespace App;

class GraphBuilder
{
    private $nodes;
    private $links;

    public function __construct($block)
    {
        $this->nodes = [];
        $this->links = [];
        $this->distinct = 0;
        $skip = true;

        if(!$block) {
            $block = app(BbAnalyzer::class)->getStartBlock();
            $skip = false;
        }

        if ($block) {
            $this->pendings = [
                (object)['item' => $block->subroutine, 'last' => null, 'skip' => $skip]
            ];
        }
    }

    public function build(int $stops = -1)
    {
        $this->stops = $stops;

        while ($pending = array_shift($this->pendings)) {
            if ($pending->item instanceof Subroutine) {
                $this->buildSubroutine($pending);
            }
            if ($pending->item instanceof Symbol) {
                $this->buildSymbol($pending);
            }

            if ($this->stops == 0) continue;
            $this->stops--;
        }
    }

    public function data()
    {
        return [
            'nodes' => array_values($this->nodes),
            'links' => $this->links,
        ];
    }

    protected function getCurrentId($pending)
    {
        if (array_key_exists(dechex($pending->item->id), $this->nodes)) {
            $this->distinct++;
            return dechex($pending->item->id) . '#' . $this->distinct;
        }

        return dechex($pending->item->id);
    }

    protected function isNotFollow($pending)
    {
        return array_key_exists(dechex($pending->item->id), $this->nodes);
    }

    protected function buildSubroutine($pending)
    {
        $notFollow = $this->isNotFollow($pending);
        $currentId  = $this->getCurrentId($pending);
        $subroutine = $pending->item;

        $node = [
            'id' => $currentId,
            'label' => $subroutine->name,
            'kind' => 'subroutine',
            'stop' => $this->stops == 0,
        ];

        if (!isset($pending->skip) || !$pending->skip) {
            $this->nodes[$currentId] = $node;
        }

        if ($pending->last) {
            $this->links[] = [
                'source' => $pending->last,
                'target' => $currentId,
                'xref' => $pending->xref,
            ];
        }

        if ($notFollow) return;

        if ($this->stops == 0) return;

        foreach($subroutine->blocks as $block) {
            foreach($block->nextFlows as $flow) {
                if ($block->jump_mnemonic == 'ret') continue;

                if ($next = $flow->block) {
                    if ($next->subroutine_id == $subroutine->id) continue;
                    $this->pendings[] = (object)['item' => $next->subroutine, 'xref' => $flow->xref, 'last' => $currentId];
                }
                if ($next = $flow->symbol) {
                    $this->pendings[] = (object)['item' => $next, 'xref' => $flow->xref, 'last' => $currentId];
                }
            }
        }
    }

    protected function buildSymbol($pending)
    {
        $notFollow = $this->isNotFollow($pending);
        $currentId  = $this->getCurrentId($pending);
        $symbol = $pending->item;

        $this->nodes[$currentId] = [
            'id' => $currentId,
            'label' => $symbol->getDisplayName(),
            'kind' => 'symbol',
        ];


        if ($pending->last) {
            $this->links[] = [
                'source' => $pending->last,
                'target' => $currentId,
                'xref' => $pending->xref,
            ];
        }

        if ($notFollow) return;
        if ($this->stops == 0) return;

        $nexts = [];

        foreach($symbol->nextFlows as $flow) {
            if ($next = $flow->block) {
                if ($next->subroutine_id != $next->id) continue;

                $this->pendings[] = (object)['item' => $next->subroutine, 'xref' => $flow->xref, 'last' => $currentId];
            }
            if ($next = $flow->symbol) {
                $this->pendings[] = (object)['item' => $next, 'xref' => $flow->xref, 'last' => $currentId];
            }
        }
    }
}
