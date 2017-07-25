<?php

namespace App;

/**
 * Class NearestValue
 * Finds the 'nearest value' of a needle in a SORTED numeric array (ascending)
 * Via https://stackoverflow.com/a/22375510/237739
 * Gist: https://gist.github.com/pepijnolivier/09435a18030419c4d15dbcf1058d536e
 */
class NearestValue {

    const ARRAY_NEAREST_DEFAULT = 0;
    const ARRAY_NEAREST_LOWER = 1;
    const ARRAY_NEAREST_HIGHER = 2;

    /**
     * Finds nearest value in numeric array. Can be used in loops.
     * Array needs to be non-assocative and sorted.
     *
     * @param array $array
     * @param int $value
     * @param int $method ARRAY_NEAREST_DEFAULT|ARRAY_NEAREST_LOWER|ARRAY_NEAREST_HIGHER
     * @return int
     */
    public static function array_numeric_sorted_nearest($array, $value, $method = self::ARRAY_NEAREST_DEFAULT)
    {
        $count = count($array);

        if($count == 0) {
            return null;
        }

        $div_step               = 2;
        $index                  = ceil($count / $div_step);
        $best_index             = null;
        $best_score             = null;
        $direction              = null;
        $indexes_checked        = Array();

        while(true) {
            if(isset($indexes_checked[$index])) {
                break ;
            }

            $curr_key = $array[$index] ?? null;
            if($curr_key === null) {
                break ;
            }

            $indexes_checked[$index] = true;

            // perfect match, nothing else to do
            if($curr_key == $value) {
                return $curr_key;
            }

            $prev_key = $array[$index - 1] ?? null;
            $next_key = $array[$index + 1] ?? null;

            switch($method) {
                default:
                case self::ARRAY_NEAREST_DEFAULT:
                    $curr_score = abs($curr_key - $value);

                    $prev_score = $prev_key !== null ? abs($prev_key - $value) : null;
                    $next_score = $next_key !== null ? abs($next_key - $value) : null;

                    if($prev_score === null) {
                        $direction = 1;
                    }else if ($next_score === null) {
                        break 2;
                    }else{
                        $direction = $next_score < $prev_score ? 1 : -1;
                    }
                    break;
                case self::ARRAY_NEAREST_LOWER:
                    $curr_score = $curr_key - $value;
                    if($curr_score > 0) {
                        $curr_score = null;
                    }else{
                        $curr_score = abs($curr_score);
                    }

                    if($curr_score === null) {
                        $direction = -1;
                    }else{
                        $direction = 1;
                    }
                    break;
                case self::ARRAY_NEAREST_HIGHER:
                    $curr_score = $curr_key - $value;
                    if($curr_score < 0) {
                        $curr_score = null;
                    }

                    if($curr_score === null) {
                        $direction = 1;
                    }else{
                        $direction = -1;
                    }
                    break;
            }

            if(($curr_score !== null) && ($curr_score < $best_score) || ($best_score === null)) {
                $best_index = $index;
                $best_score = $curr_score;
            }

            $div_step *= 2;
            $index += $direction * ceil($count / $div_step);
        }

        return $array[$best_index];
    }
}
