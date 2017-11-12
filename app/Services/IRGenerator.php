<?php

namespace App\Services;

use Exception;
use App\Instruction;
use App\Operand;
use App\Expression;
use App\Block;

class IRGenerator
{
    const MEMORY_DOMAIN = 1;
    const REGISTER_DOMAIN = 1000;

    public function generate(Block $block)
    {
        foreach ($block->instructions as $inst)
        {
            switch ($inst->mne) {
            case 'push':


            }
        }
    }

    public function createExpressionFromOperand(Operand $opnd)
    {
        if ($opnd->expression) return $opnd->expression;

        $expr = null;

        switch ($opnd->type) {
        case 'reg':
            $expr = $this->makeRegExpression($opnd->reg);
            $expr->save();

            break;
        case 'imm':
            $expr = $this->makeConstExpression($opnd->imm, $opnd->size);
            $expr->save();

            break;

        case 'mem':
            $expr = new Expression();
            $expr->size = $opnd->size;

            if ($opnd->memIsDirect()) {
                $expr->type = Expression::MEMORY_TYPE;
                $expr->domain = self::MEMORY_DOMAIN;
                $expr->const = $opnd->imm;
                $expr->save();
            } elseif ($opnd->memIsIndirect()) {
                $expr->type = Expression::DEREF_TYPE;
                $expr->save();

                $base_expr = $this->makeRegExpression($opnd->reg);
                $expr->addChild($base_expr);
            } else {
                $expr->type = Expression::DEREF_TYPE;
                $expr->save();

                $add_expr = new Expression();
                $add_expr->type = Expression::ADD_TYPE;
                $expr->addChild($add_expr);

                if ($opnd->reg) {
                    $base_expr = $this->makeRegExpression($opnd->reg);
                    $add_expr->addChild($base_expr);
                }

                if ($opnd->index) {
                    $index_expr = $this->makeRegExpression($opnd->index);
                    if ($opnd->scale) {
                        $scale_expr = $this->makeConstExpression($opnd->scale, 0);

                        $mul_expr = new Expression();
                        $mul_expr->type = Expression::MUL_TYPE;

                        $add_expr->addChild($mul_expr);

                        $mul_expr->addChild($index_expr);
                        $mul_expr->addChild($scale_expr);

                    } else {
                        $add_expr->addChild($index_expr);
                    }
                }

                if ($opnd->imm) {
                    $disp_expr = $this->makeConstExpression($opnd->imm, 0);
                    $add_expr->addChild($disp_expr);
                }
            }
            break;
        default:
            throw new Exception('Unknown operand type');
        }

        $opnd->expression_id = $expr->id;
        $opnd->save();

        return $expr;
    }


    public function makeConstExpression($const, $size)
    {
        $expr = new Expression();
        $expr->type = Expression::CONST_TYPE;

        $expr->const = $const;
        $expr->size = $size;

        return $expr;
    }

    public function makeRegExpression(string $reg)
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
        ];

        if (!isset($X86_REGISTERS[$reg])) {
            throw new Exception("Unknown register $reg");
        }

        $expr = new Expression();
        $expr->type = Expression::MEMORY_TYPE;

        $x = $X86_REGISTERS[$reg];
        $expr->domain = self::REGISTER_DOMAIN + $x[0];
        $expr->const = $x[1]; // offset
        $expr->size = $x[2];

        return $expr;
    }
}
