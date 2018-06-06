<?php
namespace JClaveau\LogicalFilter;

use JClaveau\VisibilityViolator\VisibilityViolator;

use JClaveau\LogicalFilter\Rule\AbstractOperationRule;
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
    }

    /**
     */
    public function test_construct()
    {
        $filter = new LogicalFilter(['field', 'above', 3]);

        $this->assertEquals(
            new AndRule([
                new AboveRule('field', 3),
            ]),
            $filter->getRules()
        );
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
            new InRule('field', ['a', 'b', 'c']),
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
     */
    public function test_removeNegations_basic()
    {
        $filter = new LogicalFilter();

        $filter->addRules([
            'not',
            ['field_2', 'above', 3],
        ]);

        $filter->removeNegations();

        $this->assertEquals(
            (new OrRule([
                new BelowRule('field_2', 3),
                new EqualRule('field_2', 3),
            ]))
            ->toArray(),
            $filter->toArray()
        );
    }

    /**
     */
    public function test_removeNegations_complex()
    {
        $filter = new LogicalFilter([
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

        $filter2 = new LogicalFilter([
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
            $filter2->toArray(),
            $filter->toArray()
        );
    }

    /**
     */
    public function test_rootifyDisjunctions_minimal()
    {
        $filter = new LogicalFilter();

        $filter->addRules([
            'or',
            ['field_5', 'above', 'a'],
            ['field_5', 'below', 'a'],
        ]);

        $filter
            ->rootifyDisjunctions()
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
            $filter2->toArray(),
            $filter->toArray()
        );
    }

    /**
     */
    public function test_rootifyDisjunctions_basic()
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
            $filter2->toArray(),
            $filter->toArray()
        );
    }

    /**
     */
    public function test_rootifyDisjunctions_complex()
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
                ->simplify()
                ->hasSolution()
        );

        $this->assertFalse(
            (new LogicalFilter())
                ->addRules([
                    'and',
                    ['field_5', 'equal', 'a'],
                    ['field_5', 'below', 'a'],
                ])
                ->simplify()
                ->hasSolution()
        );

        $this->assertFalse(
            (new LogicalFilter())
                ->addRules([
                    'and',
                    ['field_5', 'equal', 'a'],
                    ['field_5', 'above', 'a'],
                ])
                ->simplify()
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
                ->simplify()
                ->hasSolution()
        );
    }

    /**
     * @see https://secure.php.net/manual/en/jsonserializable.jsonserialize.php
     */
    public function test_jsonSerialize()
    {
        $this->assertEquals(
            '["or",["and",["field_5",">","a"],["field_5","<","a"]],["field_6","=","b"]]',
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

    /**
     */
    public function test_addRules_on_noSolution_filter()
    {
        $filter = (new LogicalFilter())->addRules([
            'and',
            [
                'or',
                ['field_5', 'above', 'a'],
                ['field_5', 'below', 'a'],
            ],
            ['field_5', 'equal', 'a'],
        ]);

        $filter->simplify();

        $this->assertEmpty(
            $filter->getRules()->dump()->getOperands()
        );

        try {
            $filter->addRules('a', '<', 'b');
            $this->assertFalse(false, "Adding rule to an invalid filter not forbidden");
        }
        catch (\Exception $e) {
            $e->getMessage() ==  "You are trying to add rules to a LogicalFilter which had "
                ."only contradictory rules that have been simplified.";
        }
    }

    /**
     */
    public function test_addRules_with_symbolic_operators()
    {
        $filter = (new LogicalFilter())->addRules([
            'and',
            ['field_5', '>', 'a'],
            ['field_5', '<', 'a'],
            [
                '!',
                ['field_5', '=', 'a'],
            ],
        ]);

        $this->assertEquals(
            [
                'and',
                ['field_5', '>', 'a'],
                ['field_5', '<', 'a'],
                [
                    'not',
                    ['field_5', '=', 'a'],
                ],
            ],
            $filter->toArray()
        );

    }

    /**
     */
    public function test_addRules_from_toArray()
    {
        $filter = (new LogicalFilter())->addRules([
            'and',
            ['field_5', '>', 'a'],
            ['field_5', '<', 'a'],
            [
                '!',
                ['field_5', '=', 'a'],
            ],
        ]);

        $this->assertEquals(
            $filter->toArray(),
            (new LogicalFilter($filter->toArray()))->toArray()
        );
    }

    /**
     */
    public function test_simplify_basic()
    {
        // $filter = (new LogicalFilter())->addRules([
            // 'and',
            // ['field_5', '>', 'a'],
            // ['field_6', '<', 'a'],
            // [
                // '!',
                // ['field_5', '=', 'a'],
            // ],
        // ]);

        $filter = (new LogicalFilter())->addRules([
            'and',
            ['field_5', '>', 3],
            ['field_5', '>', 5],
        ]);

        $filter->simplify();

        $this->assertTrue( $filter->getRules()->isSimplified() );

        $this->assertEquals( ['field_5', '>', 5], $filter->toArray() );
    }

    /**
     */
    public function test_simplify_nested_and_operations()
    {
        $filter = (new LogicalFilter())->addRules([
            'and',
            ['field_5', '>', 3],
            [
                'and',
                ['field_6', '>', 3],
                ['field_7', '>', 5],
            ],
        ]);

        $filter
            ->simplify( AbstractOperationRule::rootify_disjunctions )
            ;

        // $this->assertTrue( $filter->getRules()->isSimplified() );

        $this->assertEquals( [
                'and',
                ['field_5', '>', 3],
                ['field_6', '>', 3],
                ['field_7', '>', 5],
            ],
            $filter->toArray()
        );


        $filter = (new LogicalFilter())->addRules([
            'and',
            ['field_5', '>', 3],
            [
                'and',
                ['field_6', '>', 3],
                ['field_7', '>', 5],
                [
                    'and',
                    ['field_8', '>', 3],
                    ['field_9', '>', 5],
                ],
            ],
        ]);

        $filter
            ->simplify( AbstractOperationRule::rootify_disjunctions )
            ;

        // $this->assertTrue( $filter->getRules()->isSimplified() );

        $this->assertEquals( [
                'and',
                ['field_5', '>', 3],
                ['field_6', '>', 3],
                ['field_7', '>', 5],
                ['field_8', '>', 3],
                ['field_9', '>', 5],
            ],
            $filter->toArray()
        );
    }

    /**
     */
    public function test_simplify_nested_or_operations()
    {
        $filter = (new LogicalFilter())->addRules([
            'or',
            ['field_5', '>', 3],
            [
                'or',
                ['field_6', '>', 3],
                ['field_7', '>', 5],
            ],
        ]);

        $filter
            ->simplify( AbstractOperationRule::rootify_disjunctions )
            // ->getRules()->dump(!true)
            ;

        $this->assertEquals( [
                'or',
                ['field_5', '>', 3],
                ['field_6', '>', 3],
                ['field_7', '>', 5],
            ],
            $filter->toArray()
        );


        $filter = (new LogicalFilter())->addRules([
            'or',
            ['field_5', '>', 3],
            [
                'or',
                ['field_6', '>', 3],
                ['field_7', '>', 5],
                [
                    'or',
                    ['field_8', '>', 3],
                    ['field_9', '>', 5],
                ],
            ],
        ]);

        $filter
            ->simplify( AbstractOperationRule::rootify_disjunctions )
            ;

        $this->assertEquals( [
                'or',
                ['field_5', '>', 3],
                ['field_6', '>', 3],
                ['field_7', '>', 5],
                ['field_8', '>', 3],
                ['field_9', '>', 5],
            ],
            $filter->toArray()
        );

    }

    /**
     */
    public function test_simplify_multiple_equals()
    {
        $filter = (new LogicalFilter())->addRules([
            'and',
            ['field_5', '=', 3],
            ['field_5', '=', 5],
        ]);

        $filter
            ->simplify()
            // ->getRules()->dump(!true)
            ;

        $this->assertEmpty(
            $filter->getRules()->getOperands()
        );
    }

    /**
     */
    public function test_simplify_multiple_aboves()
    {
        $filter = (new LogicalFilter())->addRules([
            'and',
            ['field_5', '>', 3],
            ['field_5', '>', 5],
            ['field_5', '>', 5],
        ]);

        $filter
            ->simplify()
            // ->getRules()->dump(!true)
            ;

        $this->assertEquals(
            ['field_5', '>', 5],
            $filter->getRules()->toArray()
        );
    }

    /**
     */
    public function test_simplify_multiple_below()
    {
        $filter = (new LogicalFilter())->addRules([
            'and',
            ['field_5', '<', 3],
            ['field_5', '<', 5],
            ['field_5', '<', 5],
        ]);

        $filter
            ->simplify()
            // ->getRules()->dump(!true)
            ;

        $this->assertEquals(
            ['field_5', '<', 3],
            $filter->getRules()->toArray()
        );
    }

    /**
     */
    public function test_simplify_unifyOperands_inRecursion()
    {
        $filter = (new LogicalFilter())->addRules([
            'and',
            ['field_5', '>', 'a'],
            [
                '!',
                ['field_5', '=', 'a'],
            ],
        ]);


        $filter->simplify(
                AbstractOperationRule::remove_monooperand_operations
            )
            // ->getRules()->dump(false, false)
            ;

        $this->assertEquals( [
            'or',
            [
                'and',
                ['field_5', '>', 5],
            ],
            [
                'and',
                ['field_5', '>', 5],
                ['field_5', '<', 5],
            ],
        ], $filter->toArray() );
    }

    /**
     */
    public function test_simplify_with_negation()
    {
        $filter = (new LogicalFilter())->addRules([
            'and',
            ['field_5', '>', 'a'],
            [
                '!',
                ['field_5', '=', 'a'],
            ],
        ]);


        $filter->simplify(
                // AbstractOperationRule::remove_monooperand_operations
            )
            // ->getRules()->dump(false, false)
            ;

        $this->assertEquals( ['field_5', '>', 5], $filter->toArray() );
    }

    /**
     */
    public function test_simplify_remove_monooperands_and()
    {
        $filter = (new LogicalFilter())->addRules([
            'and',
            [
                'and',
                [
                    'and',
                    ['field_5', '=', 'a'],
                ],
            ],
        ]);


        $filter->simplify(
                // AbstractOperationRule::remove_invalid_branches
            )
            // ->getRules()->dump(false, false)
            ;

        $this->assertEquals( ['field_5', '=', 'a'], $filter->toArray() );
    }

    /**
     */
    public function test_simplify_remove_monooperands_or()
    {
        $filter = (new LogicalFilter())->addRules([
            'or',
            [
                'or',
                [
                    'or',
                    ['field_5', '=', 'a'],
                ],
            ],
        ]);


        $filter->simplify(
                // AbstractOperationRule::remove_invalid_branches
            )
            // ->getRules()->dump(false, false)
            ;

        $this->assertEquals( ['or', ['field_5', '=', 'a']], $filter->toArray() );
    }


    /**/
}