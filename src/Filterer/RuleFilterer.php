<?php
/**
 * RuleFilterer
 *
 * @package php-logical-filter
 * @author  Jean Claveau
 */
namespace JClaveau\LogicalFilter\Filterer;
use       JClaveau\LogicalFilter\LogicalFilter;
use       JClaveau\LogicalFilter\Rule\EqualRule;
use       JClaveau\LogicalFilter\Rule\BelowRule;
use       JClaveau\LogicalFilter\Rule\AboveRule;
use       JClaveau\LogicalFilter\Rule\NotEqualRule;
use       JClaveau\LogicalFilter\Rule\AbstractOperationRule;
use       JClaveau\LogicalFilter\Rule\AbstractRule;

/**
 * This filterer is intended to validate Rules.
 *
 * Manipulating the rules of a logical filter is easier with another one.
 * This filterer is used for the functions of the exposed api like
 * removeRules(), manipulateRules()
 *
 * @todo addRules()
 *
 */
class RuleFilterer extends Filterer
{
    const field       = 'field';
    const operator    = 'operator';
    const value       = 'value';
    const depth       = 'depth';
    const children    = 'children';
    const description = 'description';
    const path        = 'path';

    /**
     */
    public function getChildren($row)
    {
        if ($row instanceof AbstractOperationRule) {
            return $row->getOperands();
        }
    }

    /**
     */
    public function setChildren(&$row, $filtered_children)
    {
        if ($row instanceof AbstractOperationRule)
            return $row->setOperands( $filtered_children );
    }

    /**
     */
    public function validateRule ($field, $operator, $value, $rule, $depth, $all_operands)
    {
        // var_dump("$field, $operator, " . var_export($value, true));
        // $rule->dump();

        if ($field === self::field) {
            if (!method_exists($rule, 'getField'))
                return true;

            $value_to_compare = $rule->getField();
        }
        elseif ($field === self::operator) {
            $value_to_compare = $rule::operator;
        }
        elseif ($field === self::value) {
            $description = $rule->toArray();

            if (    count($description) === 3
                &&  is_string($description[0])
                &&  is_string($description[1]) ) {
                $value_to_compare = $description[2];
            }
            else {
                return true;
            }
        }
        elseif ($field === self::description) {
            $value_to_compare = $rule->toArray();
        }
        elseif ($field === self::depth) {
            // original $depth is lost once the filter is simplified
            throw new \InvalidArgumentException('Path rule uppport not implemented');
            // $value_to_compare = $depth;
        }
        elseif ($field === self::path) {
            // TODO the description of its parents
            throw new \InvalidArgumentException('Path rule uppport not implemented');
        }
        elseif ($field === self::children) {
            if (!method_exists($rule, 'getOperands'))
                return true;
            $value_to_compare = count( $rule->getOperands() );
        }
        else {
            throw new \InvalidArgumentException(
                "Rule filters must belong to ["
                . implode(', ', [
                    self::field,
                    self::operator,
                    self::value,
                    self::depth,
                    self::description,
                    self::children,
                    self::path,
                ])
                ."] contrary to : ".var_export($field, true)
            );
        }


        if ($operator === EqualRule::operator) {
            if ($value === null) {
                $out = is_null($value_to_compare);
            }
            else {
                // TODO support strict comparisons
                $out = $value_to_compare == $value;
            }
        }
        elseif ($operator === BelowRule::operator) {
            $out = $value_to_compare < $value;
        }
        elseif ($operator === AboveRule::operator) {
            $out = $value_to_compare > $value;
        }
        elseif ($operator === NotEqualRule::operator) {
            if ($value === null) {
                $out = !is_null($value_to_compare);
            }
            else {
                throw new \InvalidArgumentException(
                    "This case shouldn't occure with teh current simplification strategy"
                );
                // $out = $row[$field] == $operand->getValue();
            }
        }
        else {
            throw new \InvalidArgumentException(
                "Unhandled operator: " . $operator
            );
        }

        return $out;
    }

    /**
     * @param LogicalFilter $filter
     */
    public function apply( LogicalFilter $filter, $ruleTree_to_filter, $options=[] )
    {
        if ($ruleTree_to_filter instanceof AbstractRule)
            $ruleTree_to_filter = [$ruleTree_to_filter];

        if (!is_array($ruleTree_to_filter)) {
            throw new \InvalidArgumentException(
                "\$ruleTree_to_filter must be an array or an AbstractRule "
                ."instead of: " . var_export($ruleTree_to_filter, true)
            );
        }

        // Produces "Only variables should be passed by reference" on Travis
        $result = parent::apply($filter, $ruleTree_to_filter, $options);

        return reset( $result );
    }

    /**/
}