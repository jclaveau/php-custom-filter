<?php
/**
 * PhpFilterer
 *
 * @package php-logical-filter
 * @author  Jean Claveau
 */
namespace JClaveau\LogicalFilter\Filterer;

use JClaveau\LogicalFilter\LogicalFilter;
use JClaveau\LogicalFilter\Rule\EqualRule;
use JClaveau\LogicalFilter\Rule\BelowRule;
use JClaveau\LogicalFilter\Rule\AboveRule;
use JClaveau\LogicalFilter\Rule\BelowOrEqualRule;
use JClaveau\LogicalFilter\Rule\AboveOrEqualRule;
use JClaveau\LogicalFilter\Rule\NotEqualRule;
use JClaveau\LogicalFilter\Rule\InRule;
use JClaveau\LogicalFilter\Rule\NotInRule;
use JClaveau\LogicalFilter\Rule\RegexpRule;
use JClaveau\LogicalFilter\FilteredKey;
use JClaveau\LogicalFilter\FilteredValue;

/**
 */
class PhpFilterer extends Filterer
{
    /**
     */
    public function validateRule ($field, $operator, $value, $row, array $path, $all_operands, $options)
    {
        if ($field instanceof FilteredValue) {
            $value_to_validate = $field( $row );
        }
        elseif ($field instanceof FilteredKey) {
            $value_to_validate = $field( array_pop($path) );
        }
        elseif (! isset($row[(string) $field])) {
            $value_to_validate = null;
        }
        else {
            $value_to_validate = $row[ $field ];
        }

        if (EqualRule::operator === $operator) {
            if (! isset($value_to_validate)) {
                // ['field', '=', null] <=> isset($row['field'])
                // [row, '=', null] <=> $row !== null
                $result = null === $value;
            }
            else {
                // TODO support strict comparisons
                $result = $value_to_validate == $value;
            }
        }
        elseif (InRule::operator === $operator) {
            $result = in_array(
                isset($value_to_validate) ? $value_to_validate : null,
                $value
            );
        }
        elseif (BelowRule::operator === $operator) {
            if (! isset($value_to_validate)) {
                $result = false;
            }
            else {
                $result = $value_to_validate < $value;
            }
        }
        elseif (AboveRule::operator === $operator) {
            if (! isset($value_to_validate)) {
                $result = false;
            }
            else {
                $result = $value_to_validate > $value;
            }
        }
        elseif (NotEqualRule::operator === $operator) {
            if (null === $value) {
                $result = isset($value_to_validate);
            }
            else {
                $result = $value_to_validate != $value;
            }
        }
        elseif (NotInRule::operator === $operator) {
            $result = ! in_array(
                isset($value_to_validate) ? $value_to_validate : null,
                $value
            );
        }
        elseif (AboveOrEqualRule::operator === $operator) {
            if (! isset($value_to_validate)) {
                $result = false;
            }
            else {
                $result = $value_to_validate >= $value;
            }
        }
        elseif (BelowOrEqualRule::operator === $operator) {
            if (! isset($value_to_validate)) {
                $result = false;
            }
            else {
                $result = $value_to_validate <= $value;
            }
        }
        elseif (RegexpRule::operator === $operator) {
            if (! isset($value_to_validate)) {
                $result = false;
            }
            else {
                try {
                    // TODO support optionnal parameters (offest mainly) ?
                    $result = preg_match($value, $value_to_validate);
                }
                catch (\Exception $e) {
                    // The documentation of preg_match() is wrong and preg_last_error()
                    // is useless as preg_match returns 0 instead of false
                    // and then throws an exception with PHP 5.6
                    throw new \InvalidArgumentException(
                        "PCRE error ".var_export($e->getMessage(), true).
                        " while applying the regexp ".var_export($value, true)." to "
                        .var_export($value_to_validate, true)
                    );
                }

                $result = (bool) $result;
            }
        }
        else {
            throw new \InvalidArgumentException(
                "Unhandled operator: " . $operator
            );
        }

        // var_dump(
        // "$field, $operator, " . var_export($value, true)
        // . ' vs ' . var_export($value_to_validate, true) . ' => ' . var_export($result, true)
        // . "\n\n"
        // . var_export($row, true)
        // );
        // exit;
        return $result;
    }

    /**/
}
