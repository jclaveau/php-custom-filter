<?php
namespace JClaveau\LogicalFilter;

use JClaveau\VisibilityViolator\VisibilityViolator;

use JClaveau\LogicalFilter\Rule\OrRule;
use JClaveau\LogicalFilter\Rule\AndRule;
use JClaveau\LogicalFilter\Rule\NotRule;
use JClaveau\LogicalFilter\Rule\InRule;
use JClaveau\LogicalFilter\Rule\EqualRule;
use JClaveau\LogicalFilter\Rule\AboveRule;
use JClaveau\LogicalFilter\Rule\BelowRule;

class LogicalFilterTest extends \PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        ini_set('xdebug.max_nesting_level', 10000);
        ini_set('xdebug.var_display_max_depth', -1);
        ini_set('xdebug.var_display_max_children', -1);
        ini_set('xdebug.var_display_max_data', -1);
    }

    /**
     */
    public function test_addRules_simple()
    {
        $filter = new LogicalFilter();

        $filter->addRules('field', 'in', ['a', 'b', 'c']);
        // $filter->addRule('field', 'not_in', ['a', 'b', 'c']);
        $filter->addRules('field', 'above', 3);
        $filter->addRules('field', 'below', 5);

        $rules = VisibilityViolator::getHiddenProperty(
            $filter,
            'rules'
        );

        $this->assertEquals(
            new AndRule([
                new InRule('field', ['a', 'b', 'c']),
                // new NotInRule(['a', 'b', 'c']),
                new AboveRule('field', 3),
                new BelowRule('field', 5)
            ]),
            $rules
        );
    }

    /**
     */
    public function test_getRules()
    {
        $filter = new LogicalFilter();

        $filter->addRules('field', 'in', ['a', 'b', 'c']);

        $this->assertEquals(
            new AndRule([
                new InRule('field', ['a', 'b', 'c'])
            ]),
            $filter->getRules()
        );
    }

    /**
     */
    public function test_addOrRule()
    {
        $filter = new LogicalFilter();

        $filter->addRules([
            ['field', 'in', ['a', 'b', 'c']],
            'or',
            ['field', 'equal', 'e']
        ]);

        $this->assertEquals(
            new AndRule([
                new OrRule([
                    new InRule('field', ['a', 'b', 'c']),
                    new EqualRule('field', 'e')
                ]),
            ]),
            $filter->getRules()
        );
    }

    /**
     */
    public function test_addRules_with_nested_operations()
    {
        $filter = new LogicalFilter();

        $filter->addRules([
            ['field', 'in', ['a', 'b', 'c']],
            'or',
            [
                ['field', 'in', ['d', 'e']],
                'and',
                [
                    ['field_2', 'above', 3],
                    'or',
                    ['field_3', 'below', -2],
                ],
            ],
        ]);

        $this->assertEquals(
            new AndRule([
                new OrRule([
                    new InRule('field', ['a', 'b', 'c']),
                    new AndRule([
                        new InRule('field', ['d', 'e']),
                        new OrRule([
                            new AboveRule('field_2', 3),
                            new BelowRule('field_3', -2),
                        ]),
                    ]),
                ]),
            ]),
            $filter->getRules()
        );
    }

    /**
     */
    public function test_addRules_with_different_operators()
    {
        $filter = new LogicalFilter();

        // exception if different operators in the same operation
        try {
            $filter->addRules([
                ['field', 'in', ['a', 'b', 'c']],
                'or',
                [
                    ['field', 'in', ['d', 'e']],
                    'and',
                    [
                        ['field_2', 'above', 3],
                        'or',
                        ['field_3', 'below', -2],
                        'and',
                        ['field_3', 'equal', 0],
                    ],
                ],
            ]);

            $this->assertTrue(
                false,
                'No exception thrown for different operators in one operation'
            );
        }
        catch (\InvalidArgumentException $e) {

            $this->assertTrue(
                (bool) preg_match(
                    "/^Mixing different operations in the same rule level not implemented:/",
                    $e->getMessage()
                )
            );
            return;
        }
    }

    /**
     */
    public function test_addRules_without_operator()
    {
        $filter = new LogicalFilter();

        // exception if no operator in an operation
        try {
            $filter->addRules([
                ['field_2', 'above', 3],
                ['field_3', 'below', -2],
                ['field_3', 'equal', 0],
            ]);

            $this->assertTrue(
                false,
                'No exception thrown while operator is missing in an operation'
            );
        }
        catch (\InvalidArgumentException $e) {

            $this->assertTrue(
                (bool) preg_match(
                    "/^Please provide an operator for the operation: /",
                    $e->getMessage()
                )
            );
            return;
        }
    }

    /**
     */
    public function test_addRules_with_negation()
    {
        $filter = new LogicalFilter();

        $filter->addRules([
            'not',
            ['field_2', 'above', 3],
        ]);

        $this->assertEquals(
            new AndRule([
                new NotRule(
                    new AboveRule('field_2', 3)
                )
            ]),
            $filter->getRules(false)
        );

        // not with too much operands
        try {
            $filter->addRules([
                'not',
                ['field_2', 'above', 3],
                ['field_2', 'equal', 5],
            ]);

            $this->assertTrue(
                false,
                'No exception thrown if two operands for a negation'
            );
        }
        catch (\InvalidArgumentException $e) {

            $this->assertTrue(
                (bool) preg_match(
                    "/^Negations can have only one operand: /",
                    $e->getMessage()
                )
            );
            return;
        }
    }

    /**
     * @todo complexe with negations of Operations rules having more than
     * 2 operands.
     */
    public function test_removeNegations()
    {
        // simple
        $filter = new LogicalFilter();

        $filter->addRules([
            'not',
            ['field_2', 'above', 3],
        ]);

        $filter->removeNegations();

        $this->assertEquals(
            new AndRule([
                new OrRule([
                    new BelowRule('field_2', 3),
                    new EqualRule('field_2', 3),
                ])
            ]),
            $filter->getRules()
        );

        // complex
        $filter = new LogicalFilter();

        $filter->addRules([
            'or',
            ['field_1', 'below', 3],
            ['not', ['field_2', 'above', 3]],
            ['not', ['field_3', 'in', [7, 11, 13]]],
            ['not',
                [
                    'or',
                    ['field_4', 'below', 2],
                    ['field_5', 'in', ['a', 'b', 'c']],
                ],
            ],
        ]);

        $filter->removeNegations();

        $filter2 = new LogicalFilter;

        $filter2->addRules([
            'or',
            ['field_1', 'below', 3],
            // ['not', ['field_2', 'above', 3]],
            [
                'or',
                ['field_2', 'below', 3],
                ['field_2', 'equal', 3],
            ],
            // ['not', ['field_3', 'in', [7, 11, 13]]],
            [
                'and',
                [
                    'or',
                    ['field_3', 'above', 7],
                    ['field_3', 'below', 7],
                ],
                [
                    'or',
                    ['field_3', 'above', 11],
                    ['field_3', 'below', 11],
                ],
                [
                    'or',
                    ['field_3', 'above', 13],
                    ['field_3', 'below', 13],
                ],
            ],
            // ['not',
                // [
                    // 'or',
                    // ['field_4', 'below', 2],
                    // ['field_5', 'in', ['a', 'b', 'c']],
                // ],
            // ],
            [
                'and',
                [
                    'or',
                    ['field_4', 'above', 2],
                    ['field_4', 'equal', 2],
                ],
                [
                    'and',
                    [
                        'or',
                        ['field_5', 'above', 'a'],
                        ['field_5', 'below', 'a'],
                    ],
                    [
                        'or',
                        ['field_5', 'above', 'b'],
                        ['field_5', 'below', 'b'],
                    ],
                    [
                        'or',
                        ['field_5', 'above', 'c'],
                        ['field_5', 'below', 'c'],
                    ],
                ],
            ],
        ]);

        $this->assertEquals(
            $filter2->getRules(),
            $filter->getRules()
        );
    }

    /**
     */
    public function test_upLiftDisjunctions_minimal()
    {
        $filter = new LogicalFilter();

        $filter->addRules([
            'or',
            ['field_5', 'above', 'a'],
            ['field_5', 'below', 'a'],
        ]);

        $filter
            ->upLiftDisjunctions()
            // ->simplify()
            ;

        $filter2 = new LogicalFilter;

        $filter2->addRules([
            'or',
            [
                'and',
                ['field_5', 'above', 'a'],
            ],
            [
                'and',
                ['field_5', 'below', 'a'],
            ],
        ]);

        $this->assertEquals(
            $filter2->getRules(),
            $filter->getRules()
        );
    }

    /**
     */
    public function test_upLiftDisjunctions_basic()
    {
        $filter = new LogicalFilter();

        $filter->addRules([
            'and',
            [
                'or',
                ['field_4', 'above', 'a'],
                ['field_5', 'below', 'a'],
            ],
            ['field_6', 'equal', 'b'],
        ]);

        $filter->simplify();

        // $filter->getRules()->dump(!true);

        $filter2 = new LogicalFilter;
        $filter2->addRules([
            'or',
            [
                'and',
                ['field_4', 'above', 'a'],
                ['field_6', 'equal', 'b'],
            ],
            [
                'and',
                ['field_5', 'below', 'a'],
                ['field_6', 'equal', 'b'],
            ],
        ]);

        // $filter2->getRules()->dump(!true);

        $this->assertEquals(
            $filter2->getRules(),
            $filter->getRules()
        );
    }

    /**
     */
    public function test_upLiftDisjunctions_complex()
    {
        $filter = new LogicalFilter();

        // (A' || A") && (B' || B") && (C' || C") <=>
        //    (A' && B' && C') || (A' && B' && C") || (A' && B" && C') || (A' && B" && C")
        // || (A" && B' && C') || (A" && B' && C") || (A" && B" && C') || (A" && B" && C");
        $filter->addRules([
            'and',
            [
                'or',
                ['field_51', 'above', '5'],
                ['field_52', 'below', '5'],
            ],
            [
                'or',
                ['field_61', 'above', '6'],
                ['field_62', 'below', '6'],
            ],
            [
                'or',
                ['field_71', 'above', '7'],
                ['field_72', 'below', '7'],
            ],
        ]);

        $filter->simplify();

        $filter2 = new LogicalFilter;
        $filter2->addRules([
            'or',
            [
                'and',
                ['field_51', 'above', '5'],
                ['field_61', 'above', '6'],
                ['field_71', 'above', '7'],
            ],
            [
                'and',
                ['field_52', 'below', '5'],
                ['field_61', 'above', '6'],
                ['field_71', 'above', '7'],
            ],
            [
                'and',
                ['field_51', 'above', '5'],
                ['field_62', 'below', '6'],
                ['field_71', 'above', '7'],
            ],
            [
                'and',
                ['field_52', 'below', '5'],
                ['field_62', 'below', '6'],
                ['field_71', 'above', '7'],
            ],
            [
                'and',
                ['field_51', 'above', '5'],
                ['field_61', 'above', '6'],
                ['field_72', 'below', '7'],
            ],
            [
                'and',
                ['field_52', 'below', '5'],
                ['field_61', 'above', '6'],
                ['field_72', 'below', '7'],
            ],
            [
                'and',
                ['field_51', 'above', '5'],
                ['field_62', 'below', '6'],
                ['field_72', 'below', '7'],
            ],
            [
                'and',
                ['field_52', 'below', '5'],
                ['field_62', 'below', '6'],
                ['field_72', 'below', '7'],
            ],
        ]);

        $this->assertEquals(
            $filter->toArray(),
            $filter2->toArray()
        );
    }

    /**
     */
    public function test_hasSolution()
    {
        $this->assertFalse(
            (new LogicalFilter())
                ->addRules([
                    'and',
                    ['field_5', 'above', 'a'],
                    ['field_5', 'below', 'a'],
                ])
                ->hasSolution()
        );

        $this->assertFalse(
            (new LogicalFilter())
                ->addRules([
                    'and',
                    ['field_5', 'equal', 'a'],
                    ['field_5', 'below', 'a'],
                ])
                ->hasSolution()
        );

        $this->assertFalse(
            (new LogicalFilter())
                ->addRules([
                    'and',
                    ['field_5', 'equal', 'a'],
                    ['field_5', 'above', 'a'],
                ])
                ->hasSolution()
        );

        $this->assertTrue(
            (new LogicalFilter())
                ->addRules([
                    'or',
                    [
                        'and',
                        ['field_5', 'above', 'a'],
                        ['field_5', 'below', 'a'],
                    ],
                    ['field_6', 'equal', 'b'],
                ])
                ->hasSolution()
        );
    }

    /**
     * @see https://secure.php.net/manual/en/jsonserializable.jsonserialize.php
     */
    public function test_jsonSerialize()
    {
        $this->assertEquals(
            '["and",["or",["and",["field_5",">","a"],["field_5","<","a"]],["field_6","=","b"]]]',
            json_encode(
                (new LogicalFilter())->addRules([
                    'or',
                    [
                        'and',
                        ['field_5', 'above', 'a'],
                        ['field_5', 'below', 'a'],
                    ],
                    ['field_6', 'equal', 'b'],
                ])
            )
        );
    }

    /**
     */
    public function test_copy()
    {
        $filter = (new LogicalFilter())->addRules([
            'or',
            [
                'and',
                ['field_5', 'above', 'a'],
                ['field_5', 'below', 'a'],
            ],
            ['field_6', 'equal', 'b'],
        ]);

        $filter2 = $filter->copy();

        $this->assertEquals($filter, $filter2);

        $this->assertNotEquals(
            spl_object_hash($filter->getRules(false)),
            spl_object_hash($filter2->getRules(false))
        );
    }

    /**/
}
