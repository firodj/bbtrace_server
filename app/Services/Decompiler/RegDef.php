<?php

namespace App\Services\Decompiler;

use App\Services\Ranger;

class RegDef
{
    /**
     * @var string $reg
     */
    public $reg;

    /**
     * @var array<int, RegDefUse> $defs
     */
    public $defs;

    /**
     * @var array $reg_shareds
     */
    public static $reg_shareds = null;

    /**
     * @var array $reg_overlaps
     */
    public static $reg_overlaps = null;

    const X86_REG_DOMAIN = [
        'eip' => [1000, 0, 32],
        'ip'  => [1000, 0, 16],

        'eflags' => [1001, 0, 32],
        'flags'  => [1001, 0, 16],

        'cf' => [1001,  0,  1], // Carry flag
        'pf' => [1001,  2,  1], // Parity flag
        'af' => [1001,  4,  1], // Adjust flag
        'zf' => [1001,  6,  1], // Zero flag
        'sf' => [1001,  7,  1], // Sign flag
        'if' => [1001,  9,  1], // Interrupt enable flag (X)
        'df' => [1001, 10,  1], // Direction flag (C)
        'of' => [1001, 11,  1], // Overflow flag

        'eax' => [1002, 0, 32],
        'ax'  => [1002, 0, 16],
        'al'  => [1002, 0, 8],
        'ah'  => [1002, 8, 8],

        'ecx' => [1003, 0, 32],
        'cx'  => [1003, 0, 16],
        'cl'  => [1003, 0, 8],
        'ch'  => [1003, 8, 8],

        'edx' => [1004, 0, 32],
        'dx'  => [1004, 0, 16],
        'dl'  => [1004, 0, 8],
        'dh'  => [1004, 8, 8],

        'ebx' => [1005, 0, 32],
        'bx'  => [1005, 0, 16],
        'bl'  => [1005, 0, 8],
        'bh'  => [1005, 8, 8],

        'esp' => [1006, 0, 32],
        'sp'  => [1006, 0, 16],
        'spl'  => [1006, 0, 8],

        'ebp' => [1007, 0, 32],
        'bp'  => [1007, 0, 16],
        'bpl'  => [1007, 0, 8],

        'esi' => [1008, 0, 32],
        'si'  => [1008, 0, 16],

        'edi' => [1009, 0, 32],
        'di'  => [1009, 0, 16],

        'es'  => [1010, 0, 16],
        'cs'  => [1011, 0, 16],
        'ss'  => [1012, 0, 16],
        'ds'  => [1013, 0, 16],
        'fs'  => [1014, 0, 16],
        'gs'  => [1015, 0, 16],

        'st0'  => [1016, 0*80, 80],
        'st1'  => [1016, 1*80, 80],
        'st2'  => [1016, 2*80, 80],
        'st3'  => [1016, 3*80, 80],
        'st4'  => [1016, 4*80, 80],
        'st5'  => [1016, 5*80, 80],
        'st6'  => [1016, 6*80, 80],
        'st7'  => [1016, 7*80, 80],

        'fpsw' => [1017, 0, 16],
    ];

    public static function regDomain(string $reg)
    {
        return self::X86_REG_DOMAIN[$reg];
    }

    public static function regShared(string $reg)
    {
        if (is_null(self::$reg_shareds)) {
            $shared = [];
            foreach (self::X86_REG_DOMAIN as $_reg => $domain) {
                $d = $domain[0];
                if (!array_key_exists($d, $shared)) {
                    $shared[$d] = [];
                }
                $shared[$d][] = $_reg;
            }

            self::$reg_shareds = [];
            foreach (self::X86_REG_DOMAIN as $_reg => $domain) {
                $d = $domain[0];
                self::$reg_shareds[$_reg] = array_filter($shared[$d],
                    function ($x) use($_reg) { return $x != $_reg; }
                );
            }
        }

        return self::$reg_shareds[$reg];
    }

    public static function regOverlap(string $reg)
    {
        if (is_null(self::$reg_overlaps)) {
            self::$reg_overlaps = [];

            foreach (self::X86_REG_DOMAIN as $_reg => $domain) {
                $r1 = Ranger::fromDomain($domain);
                $overlap = [];

                foreach (RegDef::regShared($_reg) as $reg_shared) {
                    $_domain = RegDef::regDomain($reg_shared);
                    $r2 = Ranger::fromDomain($_domain);

                    if (Ranger::isOverlap($r1, $r2)) {
                        $overlap[] = $reg_shared;
                    }
                }

                self::$reg_overlaps[$_reg] = $overlap;
            }
        }

        return self::$reg_overlaps[$reg];
    }

    public function __construct($reg)
    {
        $this->reg = $reg;
        $this->defs = [
            0 => new RegDefUse($reg, 0)
        ];
    }

    public function addDef(int $inst_id, State $state)
    {
        $reg_defuse = $this->latestDef($state);

        if ($reg_defuse->inst_id !== $inst_id) {
            $r = max(array_keys($this->defs)) + 1;

            $reg_defuse = new RegDefUse($this->reg, $r, $inst_id);

            $state->setRev($this->reg, $reg_defuse->rev);

            $this->defs[$r] = $reg_defuse;
        }

        return $reg_defuse;
    }

    public function latestDef(State $state)
    {
        // $r = max(array_keys($this->defs));
        $r = $state->getRev($this->reg);
        return $this->defs[$r];
    }
}
