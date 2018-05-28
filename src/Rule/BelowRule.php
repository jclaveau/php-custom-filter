<?php
namespace JClaveau\LogicalFilter\Rule;

/**
 * a < x
 */
class BelowRule extends AbstractAtomicRule
{
    /** @var string operator */
    const operator = '<';

    /** @var scalar $minimum */
    protected $maximum;

    /**
     * @param string $field The field to apply the rule on.
     * @param array  $value The value the field can below to.
     */
    public function __construct( $field, $maximum )
    {
        if (!is_scalar($maximum)) {
            throw new \InvalidArgumentException(
                "Maximum parameter must be a scalar"
            );
        }

        $this->field   = $field;
        $this->maximum = $maximum;
    }

    /**
     * Checks if the rule do not expect the value to be above infinity.
     *
     * @return bool
     */
    public function hasSolution()
    {
        return !(is_infinite( $this->maximum ) && $this->maximum < 0)
            && (!is_numeric( $this->maximum ) || !is_nan( $this->maximum ));
    }

    /**
     */
    public function getMaximum()
    {
        return $this->maximum;
    }

    /**
     */
    public function toArray($debug=false)
    {
        return [
            $this->field,
            $debug ? get_class($this).':'.spl_object_id($this) : self::operator,
            $this->maximum,
        ];
    }

    /**/
}
