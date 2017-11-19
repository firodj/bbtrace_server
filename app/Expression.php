<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Exception;

/**
    Schema::create('expressions', function (Blueprint $table)
    {
        $table->increments('id');
        $table->integer('operand_id')->index();
        $table->integer('parent_id')->nullable()->index();
        $table->tinyInteger('pos')->default(0);
        $table->string('type');
        $table->integer('size')->default(0);
        $table->bigInteger('const')->nullable();
        $table->integer('domain')->nullable();
    });
 */

class Expression extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    const MEMORY_DOMAIN = 1;

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
            1000 => 'ip',
            1001 => 'flags',
            1002 => 'ax',
            1003 => 'cx',
            1004 => 'dx',
            1005 => 'bx',
            1006 => 'sp',
            1007 => 'bp',
            1008 => 'si',
            1009 => 'di',
            1010 => 'es',
            1011 => 'cs',
            1012 => 'ss',
            1013 => 'ds',
            1014 => 'fs',
            1015 => 'gs',
            1016 => 'st',
        ];

        if ($domain == Expression::MEMORY_DOMAIN) {
            return 'data';
        } else {
            return $DOMAIN_NAMES[$domain] ?? null;
        }
    }

    public static function registerDomain(string $reg)
    {
        $x = self::X86_REG_DOMAIN[$reg] ?? null;
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
