<?php

namespace App\Decompiler;

class State
{
    public $esp;
    public $reg_changes = [];

    public $esp_stack = [];

    public function __construct()
    {
        $this->esp = 0;
    }

    public function checkReadsWrites($mne, $analyzer)
    {
        $address = $mne->ins->address;
        $block_id = $mne->block_id;

        foreach ($mne->reads as $reg => $opnd)
        {
            // create reg for reg_revisions
            if (! array_key_exists($reg,  $analyzer->reg_revisions)) {
                $analyzer->reg_revisions[$reg] = [
                    -1 => (object)['read_by' => []]
                ];
            }

            // check last rev changes for reg in this state
            if (array_key_exists($reg, $this->reg_changes)) {
                $rev = $this->reg_changes[$reg];
            } else {
                $rev = max(array_keys($analyzer->reg_revisions[$reg]));
                $this->reg_changes[$reg] = $rev;
            }

            // apply operand rev
            $opnd->rev = $rev;

            // append read_by
            array_push($analyzer->reg_revisions[$reg][$rev]->read_by,
                (object)[
                    'address' => $address,
                    'block_id' => $block_id
                ]
            );
        }

        foreach ($mne->writes as $reg => $opnd)
        {
            // skip when rev already stes, eg. on process
            if (!is_null($opnd->rev)) continue;

            // create reg for reg_revisions
            if (! array_key_exists($reg, $analyzer->reg_revisions)) {
                $analyzer->reg_revisions[$reg] = [];
            }

            // check last rev changes for reg in this state then increments
            if (count($analyzer->reg_revisions[$reg])) {
                $rev = max(array_keys($analyzer->reg_revisions[$reg])) + 1;
            } else {
                $rev = 0;
            }

            $analyzer->reg_revisions[$reg][$rev] = (object)['write_by' => null, 'read_by' => []];
            $this->reg_changes[$reg] = $rev;

            // apply operand rev
            $opnd->rev = $rev;

            // append write_by
            $analyzer->reg_revisions[$reg][$rev]->write_by =
                (object)[
                    'address' => $address,
                    'block_id' => $block_id
                ];
        }
    }

    public function pushStack($opnd)
    {
        $this->esp -= 4;
        $this->esp_stack[$this->esp] = $opnd;
    }

    public function popStack()
    {
        $opnd = $this->esp_stack[$this->esp];
        $this->esp += 4;

        return $opnd;
    }
}
