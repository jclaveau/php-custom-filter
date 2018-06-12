<?php
namespace JClaveau\LogicalFilter\Rule;

/**
 * a > x
 */
class AboveRule extends AbstractAtomicRule
{
    /** @var string operator */
    const operator = '>';

    /** @var scalar $minimum */
    protected $minimum;

    /**
     * @param string $field The field to apply the rule on.
     * @param array  $value The value the field can above to.
     */
    public function __construct( $field, $minimum )
    {
        if (!is_scalar($minimum) && $minimum !== null) {
            throw new \InvalidArgumentException(
                "Minimum parameter must be a scalar or null instead of: "
                .var_export($minimum, true)
            );
        }

        $this->field   = $field;
        $this->minimum = $minimum;
    }

    /**
     */
    public function getMinimum()
    {
        return $this->minimum;
    }

    /**
     * Checks if the rule do not expect the value to be above infinity.
     *
     * @return bool
     */
    public function hasSolution()
    {
        // if minimum is null, the rule is equivalent to true
        if (is_numeric( $this->minimum )) {
            if (is_nan( $this->minimum ))
                return false;

            if (is_infinite( $this->minimum ) && $this->minimum > 0)
                return false;
        }

        return true;
    }

    /**
     */
    public function toArray($debug=false)
    {
        return [
            $this->field,
            $debug ? $this->getInstanceId() : self::operator,
            $this->minimum,
        ];
    }

    /**/
}
