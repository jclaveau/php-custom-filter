<?php
namespace JClaveau\LogicalFilter\Rule;

use       JClaveau\VisibilityViolator\VisibilityViolator;

/**
 * Logical inclusive disjunction
 *
 * This class represents a rule that expect a value to be one of the list of
 * possibilities only.
 */
class OrRule extends AbstractOperationRule
{
    /** @var string operator */
    const operator = 'or';

    /**
     * Remove AndRules operands of AndRules and OrRules of OrRules.
     */
    public function removeSameOperationOperands(array $simplification_options)
    {
        foreach ($this->operands as $i => &$operand) {
            if ( ! is_a($operand, OrRule::class)) {
                continue;
            }

            if ( ! $operand->isNormalizationAllowed($simplification_options) ) {
                continue;
            }

            // Id AND is an operand on AND they can be merge (and the same with OR)
            foreach ($operand->getOperands() as $sub_operand) {
                $this->addOperand( $sub_operand->copy() );
            }
            unset($this->operands[$i]);

            // possibility of mono-operand or dupicates
            $has_been_changed = true;
        }

        return ! empty($has_been_changed);
    }

    /**
     * Replace all the OrRules of the RuleTree by one OrRule at its root.
     *
     * @todo return $this (implements a Rule monad?)
     *
     * @return $this
     */
    public function rootifyDisjunctions($simplification_options)
    {
        if ( ! $this->isNormalizationAllowed($simplification_options)) {
            return $this->copy();
        }

        $this->moveSimplificationStepForward( self::rootify_disjunctions, $simplification_options );

        $upLiftedOperands = [];
        foreach ($this->getOperands() as $operand) {
            $operand = $operand->copy();
            if ($operand instanceof AbstractOperationRule) {
                $operand = $operand->rootifyDisjunctions($simplification_options);
            }

            if ($operand instanceof OrRule && $operand->isNormalizationAllowed($simplification_options)) {
                foreach ($operand->getOperands() as $subOperand) {
                    $upLiftedOperands[] = $subOperand;
                }
            }
            else {
                $upLiftedOperands[] = $operand;
            }
        }

        return new OrRule($upLiftedOperands);
    }

    /**
     * @param array $options   + show_instance=false Display the operator of the rule or its instance id
     *
     * @return array
     */
    public function toArray(array $options=[])
    {
        $default_options = [
            'show_instance' => false,
            'sort_operands' => false,
            'semantic'      => false,
        ];
        foreach ($default_options as $default_option => &$default_value) {
            if ( ! isset($options[ $default_option ])) {
                $options[ $default_option ] = $default_value;
            }
        }

        if ( ! $options['show_instance'] && ! empty($this->cache['array'])) {
            return $this->cache['array'];
        }

        $operands_as_array = [
            $options['show_instance'] ? $this->getInstanceId() : self::operator,
        ];

        $operands = $this->operands;
        if ($options['semantic']) {
            // Semantic array: ['operator', 'semantic_id_of_operand1', 'semantic_id_of_operand2', ...]
            // with sorted semantic ids
            $operands_semantic_ids = array_keys($operands);
            sort($operands_semantic_ids);
            return array_merge(
                [self::operator],
                $operands_semantic_ids
            );
        }
        else {
            foreach ($operands as $operand) {
                $operands_as_array[] = $operand->toArray($options);
            }

            if ( ! $options['show_instance']) {
                return $this->cache['array'] = $operands_as_array;
            }
            else {
                return $operands_as_array;
            }
        }
    }

    /**
     */
    public function toString(array $options=[])
    {
        $operator = self::operator;
        if ( ! $this->operands) {
            return $this->cache['string'] = "['{$operator}']";
        }

        $indent_unit = isset($options['indent_unit']) ? $options['indent_unit'] : '';
        $line_break  = $indent_unit ? "\n" : '';

        $out = "['{$operator}',$line_break";

        foreach ($this->operands as $operand) {
            $out .= implode("\n", array_map(function($line) use (&$indent_unit) {
                return $indent_unit.$line;
            }, explode("\n", $operand->toString($options)) )) . ",$line_break";
        }

        $out .= ']';

        return $this->cache['string'] = $out;
    }

    /**
     * + if A > 2 && A > 1 <=> A > 2
     * + if A < 2 && A < 1 <=> A < 1
     */
    protected static function simplifySameOperands(array $operandsByFields)
    {
        // unifying same operands
        foreach ($operandsByFields as $field => $operandsByOperator) {
            foreach ($operandsByOperator as $operator => $operands) {
                unset($previous_operand);

                try {
                    if (AboveRule::operator == $operator) {
                        usort($operands, function( AboveRule $a, AboveRule $b ) {
                            if (null === $a->getMinimum()) {
                                return 1;
                            }

                            if (null === $b->getMinimum()) {
                                return -1;
                            }

                            if ($a->getMinimum() < $b->getMinimum()) {
                                return -1;
                            }

                            return 1;
                        });
                        $operands = [reset($operands)];
                    }
                    elseif (BelowRule::operator == $operator) {
                        usort($operands, function( BelowRule $a, BelowRule $b ) {
                            if (null === $a->getMaximum()) {
                                return 1;
                            }

                            if (null === $b->getMaximum()) {
                                return -1;
                            }

                            if ($a->getMaximum() > $b->getMaximum()) {
                                return -1;
                            }

                            return 1;
                        });
                        $operands = [reset($operands)];
                    }
                    elseif (InRule::operator == $operator) {
                        $first_in = reset($operands);

                        foreach ($operands as $i => $next_in) {
                            if ($first_in === $next_in) {
                                continue;
                            }

                            $first_in->setPossibilities( array_merge(
                                $first_in->getPossibilities(),
                                $next_in->getPossibilities()
                            ) );

                            unset($operands[$i]);
                        }
                    }
                }
                catch (\Exception $e) {
                    VisibilityViolator::setHiddenProperty($e, 'message', $e->getMessage() . "\n" . var_export([
                            'operands'          => $operands,
                        ], true)
                    );

                    // \Debug::dumpJson($this->toArray(), true);
                    throw $e;
                }

                $operandsByFields[ $field ][ $operator ] = $operands;
            }
        }

        return $operandsByFields;
    }

    /**
     * Removes rule branches that cannot produce result like:
     * A = 1 && ( (B < 2 && B > 3) || (C = 8 && C = 10) ) <=> A = 1
     *
     * @return OrRule
     */
    public function removeInvalidBranches(array $simplification_options)
    {
        if ( ! $this->isNormalizationAllowed($simplification_options)) {
            return $this;
        }

        $this->moveSimplificationStepForward(self::remove_invalid_branches, $simplification_options);

        foreach ($this->operands as $i => $operand) {
            if ($operand instanceof AndRule || $operand instanceof OrRule) {
                $this->operands[$i] = $operand->removeInvalidBranches($simplification_options);
                if ( ! $this->operands[$i]->getOperands()) {
                    unset($this->operands[$i]);
                    continue;
                }
            }
            else {
                if ( ! $this->operands[$i]->hasSolution()) {
                    unset($this->operands[$i]);
                }
            }
        }

        return $this;
    }

    /**
     * Checks if the tree below the current OperationRule can have solutions
     * or if it contains contradictory rules.
     *
     * @return bool If the rule can have a solution or not
     */
    public function hasSolution(array $simplification_options=[])
    {
        if ( ! $this->isNormalizationAllowed($simplification_options)) {
            return true;
        }

        $operands = $this->getOperands();
        if (    (count($operands) == 1 && ! reset($operands)->hasSolution()) // skip simplification case after call of addMinimalCase() (which seems to have unwanted side effect)
            && ! $this->simplicationStepReached(self::simplified)) {
            throw new \LogicException(
                "hasSolution has no sens if the rule is not simplified instead of being at: "
                .var_export($this->current_simplification_step, true)
            );
        }

        // If there is no remaining operand in an OrRule, it means it has
        // no solution.
        return ! empty($operands);
    }

    /**
     * This method is meant to be used during simplification that would
     * need to change the class of the current instance by a normal one.
     *
     * @return OrRule The current instance (of or or subclass) or a new OrRule
     */
    public function setOperandsOrReplaceByOperation($new_operands)
    {
        try {
            return $this->setOperands( $new_operands );
        }
        catch (\LogicException $e) {
            return new OrRule( $new_operands );
        }
    }

    /**/
}
