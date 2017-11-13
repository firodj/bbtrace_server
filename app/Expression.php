<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Exception;

class Expression extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    const MEMORY_DOMAIN = 1;
    const REGISTER_DOMAIN = 1000;

    const MEMORY_TYPE = 'memory';
    const CONST_TYPE = 'const';
    const DEREF_TYPE = 'deref';
    const ADD_TYPE = 'add';
    const SUB_TYPE = 'sub';
    const MUL_TYPE = 'mul';
    const AND_TYPE = 'and';

    public function parent()
    {
        return $this->belongsTo(Expression::class);
    }

    public function expressions()
    {
        return $this->hasMany(Expression::class, 'parent_id')->orderBy('pos', 'asc');
    }

    public function addExpression(Expression $subexpr)
    {
        $query = $this->expressions()->select(app('db')->raw('max(pos) as max_pos'));
        $max_pos = $query->first();

        if ($max_pos && !is_null($max_pos->max_pos)) $pos = $max_pos->max_pos + 1;
        else $pos = 0;

        $subexpr->pos = $pos;
        $subexpr->save();

        $this->expressions()->save($subexpr);
    }

    public static function sizeName(int $size)
    {
        switch ($size) {
        case 0: return '';
        case 1: return 't';
        case 8: return 'b';
        case 16: return 'w';
        case 32: return 'd';
        case 64: return 'q';
        case 80: return 'x';
        }
        return '?';
    }

    public function toString()
    {
        $x = $this->expressions->map(function ($expr) { return $expr->toString(); });

        switch($this->type) {
        case self::MEMORY_TYPE:
            $name = self::domainName($this->domain);
            if ($this->domain == self::MEMORY_DOMAIN) {
                return sprintf("%s_%d'%s", $name, $this->const, Expression::sizeName($this->size));
            } else {
                return sprintf("%s.%d'%s", $name, $this->const, Expression::sizeName($this->size));
            }
        case self::CONST_TYPE:
            return sprintf("%d'%s", $this->const, Expression::sizeName($this->size));
        case self::DEREF_TYPE:
            return sprintf("@[%s]'%s", $x[0], Expression::sizeName($this->size));

        case self::ADD_TYPE:
            return sprintf("(%s)", $x->implode(' + '));
        case self::SUB_TYPE:
            return sprintf("(%s)", $x->implode(' - '));
        case self::MUL_TYPE:
            return sprintf("(%s)", $x->implode(' * '));

        case self::AND_TYPE:
            return sprintf("(%s)", $x->implode(' & '));
        }

        throw new Exception('Unknown expression to string: '.$this->type);
    }

    public static function domainName(int $domain)
    {
        static $DOMAIN_NAMES = [
            0 => 'ip',
            1 => 'flags',
            2 => 'ax',
            3 => 'cx',
            4 => 'dx',
            5 => 'bx',
            6 => 'sp',
            7 => 'bp',
            8 => 'si',
            9 => 'di',
            10 => 'es',
            11 => 'cs',
            12 => 'ss',
            13 => 'ds',
            14 => 'fs',
            15 => 'gs',
            16 => 'st',
            20 => 'tmp'
        ];

        if ($domain >= Expression::REGISTER_DOMAIN) {
            return $DOMAIN_NAMES[$domain - Expression::REGISTER_DOMAIN] ?? null;
        } else if ($domain == Expression::MEMORY_DOMAIN) {
            return 'data';
        }
    }

    public static function registerDomain(string $reg)
    {
        static $X86_REGISTERS = [
            'eip' => [0, 0, 32],
            'ip'  => [0, 0, 16],

            'eflags' => [1, 0, 32],
            'flags'  => [1, 0, 16],

            'cf' => [1,  0,  1], // Carry flag
            'pf' => [1,  2,  1], // Parity flag
            'af' => [1,  4,  1], // Adjust flag
            'zf' => [1,  6,  1], // Zero flag
            'sf' => [1,  7,  1], // Sign flag
            'if' => [1,  9,  1], // Interrupt enable flag (X)
            'df' => [1, 10,  1], // Direction flag (C)
            'of' => [1, 11,  1], // Overflow flag

            'eax' => [2, 0, 32],
            'ax'  => [2, 0, 16],
            'al'  => [2, 0, 8],
            'ah'  => [2, 8, 8],

            'ecx' => [3, 0, 32],
            'cx'  => [3, 0, 16],
            'cl'  => [3, 0, 8],
            'ch'  => [3, 8, 8],

            'edx' => [4, 0, 32],
            'dx'  => [4, 0, 16],
            'dl'  => [4, 0, 8],
            'dh'  => [4, 8, 8],

            'ebx' => [5, 0, 32],
            'bx'  => [5, 0, 16],
            'bl'  => [5, 0, 8],
            'bh'  => [5, 8, 8],

            'esp' => [6, 0, 32],
            'sp'  => [6, 0, 16],

            'ebp' => [7, 0, 32],
            'bp'  => [7, 0, 16],

            'esi' => [8, 0, 32],
            'si'  => [8, 0, 16],

            'edi' => [9, 0, 32],
            'di'  => [9, 0, 16],

            'es'  => [10, 0, 16],
            'cs'  => [11, 0, 16],
            'ss'  => [12, 0, 16],
            'ds'  => [13, 0, 16],
            'fs'  => [14, 0, 16],
            'gs'  => [15, 0, 16],

            'st0'  => [16, 0*80, 80],
            'st1'  => [16, 1*80, 80],
            'st2'  => [16, 2*80, 80],
            'st3'  => [16, 3*80, 80],
            'st4'  => [16, 4*80, 80],
            'st5'  => [16, 5*80, 80],
            'st6'  => [16, 6*80, 80],
            'st7'  => [16, 7*80, 80],

            'tmpl' => [20, 0, 8],
            'tmp'  => [20, 0, 16],
            'etmp' => [20, 0, 32],
        ];

        $x = $X86_REGISTERS[$reg] ?? null;
        if ($x) $x[0] = Expression::REGISTER_DOMAIN + $x[0];

        return $x;
    }

    public static function createExpressionFromOperand(Operand $opnd)
    {
        if ($expr = $opnd->expression()->first()) {
            $expr->delete();
        }

        switch ($opnd->type) {
        case 'reg':
            $expr = self::makeRegExpression($opnd->reg);
            $opnd->expression()->save($expr);
            break;

        case 'imm':
            $expr = self::makeConstExpression($opnd->imm, $opnd->size);
            $opnd->expression()->save($expr);
            break;

        case 'mem':
            $expr = new Expression();
            $expr->size = $opnd->size;

            if ($opnd->memIsDirect()) {
                $expr->type = Expression::MEMORY_TYPE;
                $expr->domain = Expression::MEMORY_DOMAIN;
                $expr->const = $opnd->imm;
                $opnd->expression()->save($expr);

            } elseif ($opnd->memIsIndirect()) {
                $expr->type = Expression::DEREF_TYPE;
                $opnd->expression()->save($expr);

                $base_expr = Expression::makeRegExpression($opnd->reg);
                $expr->addExpression($base_expr);
            } else {
                $expr->type = Expression::DEREF_TYPE;
                $opnd->expression()->save($expr);

                $add_expr = new Expression();
                $add_expr->type = Expression::ADD_TYPE;
                $expr->addExpression($add_expr);

                if ($opnd->reg) {
                    $base_expr = Expression::makeRegExpression($opnd->reg);
                    $add_expr->addExpression($base_expr);
                }

                if ($opnd->index) {
                    $index_expr = Expression::makeRegExpression($opnd->index);
                    if ($opnd->scale) {
                        $scale_expr = Expression::makeConstExpression($opnd->scale, 0);

                        $mul_expr = new Expression();
                        $mul_expr->type = Expression::MUL_TYPE;

                        $add_expr->addExpression($mul_expr);

                        $mul_expr->addExpression($index_expr);
                        $mul_expr->addExpression($scale_expr);

                    } else {
                        $add_expr->addExpression($index_expr);
                    }
                }

                if ($opnd->imm) {
                    $disp_expr = Expression::makeConstExpression($opnd->imm, 0);
                    $add_expr->addExpression($disp_expr);
                }
            }
            break;
        default:
            throw new Exception('Unknown operand type: ' . $opnd->type);
        }

        return $expr;
    }

    public static function makeConstExpression(int $const, int $size)
    {
        $expr = new Expression();
        $expr->type = Expression::CONST_TYPE;

        $expr->const = $const;
        $expr->size = $size;

        return $expr;
    }


    public static function makeRegExpression(string $reg)
    {
        $x = Expression::registerDomain($reg);
        if (!isset($x)) {
            throw new Exception("Unknown register $reg");
        }

        $expr = new Expression();
        $expr->type = Expression::MEMORY_TYPE;
        $expr->domain = $x[0];
        $expr->const = $x[1]; // offset
        $expr->size = $x[2];

        return $expr;
    }
}
