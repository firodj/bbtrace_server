<?php

class GraphBuilderTest extends TestCase
{
    public function testBuild()
    {
        $builder = new App\Services\GraphBuilder();

        $builder->build();
    }

    public function testRetrieve()
    {
        $builder = new App\GraphBuilder();

        $ret = $builder->retrieve();

        $node_ids = array_map(function($x) { return $x['id']; }, $ret['nodes']);

        foreach($ret['links'] as $link) {
            $this->assertTrue(in_array($link['source_id'], $node_ids), "Link #$link[id]: Source #$link[source_id] not exists in nodes.");
            $this->assertTrue(in_array($link['target_id'], $node_ids), "Link #$link[id]: Target #$link[target_id] not exists in nodes.");
        }
    }
}
