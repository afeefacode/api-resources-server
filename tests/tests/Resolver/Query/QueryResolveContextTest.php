<?php

namespace Afeefa\ApiResources\Tests\Resolver\Query;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\Query\QueryResolveContext;
use Afeefa\ApiResources\Resolver\QueryAttributeResolver;
use Afeefa\ApiResources\Resolver\QueryRelationResolver;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Tests\Resolver\TestWatcher;
use Afeefa\ApiResources\Type\Type;

use Closure;

class QueryResolveContextTest extends ApiResourcesTest
{
    private TestWatcher $testWatcher;

    protected function setUp(): void
    {
        parent::setup();

        $this->testWatcher = new TestWatcher();
    }

    public function test_simple()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
            $fields->relation('some_relation', T('TEST'), $this->createRelationResolver());
        });

        $resolveContext = $this->createResolveContext($type, [
            'name' => true,
            'some_relation' => [
                'name' => true
            ]
        ]);

        $expectedFields = [
            'name' => true,
            'some_relation' => [
                'name' => true
            ]
        ];

        $this->assertEquals($expectedFields, $resolveContext->getRequestedFields());
        $this->assertEquals(['id', 'name'], $resolveContext->getSelectFields());
    }

    public function test_normalizes()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
            $fields->relation('some_relation', T('TEST'), $this->createRelationResolver());
            $fields->relation('relation2', T('TEST'), $this->createRelationResolver());
        });

        $fields = [
            '@TEST' => [ // on type
                'name' => true,
                'count_some_relation' => true,
                'some_relation' => [
                    'name' => true,
                    'some_relation' => true
                ],
                'relation2' => true
            ]
        ];

        $resolveContext = $this->createResolveContext($type, $fields);

        $expectedFields = [
            'name' => true,
            'count_some_relation' => true,
            'some_relation' => [
                'name' => true,
                'some_relation' => true
            ],
            'relation2' => []
        ];

        $this->assertSame($expectedFields, $resolveContext->getRequestedFields());
        $this->assertEquals(['id', 'name'], $resolveContext->getSelectFields());
    }

    public function test_normalizes2()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
            $fields->relation('some_relation', T('TEST2'), $this->createRelationResolver());
            $fields->relation('other_relation', T('TEST2'), $this->createRelationResolver());
        });

        $type2 = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
            $fields->relation('some_relation2', T('TEST'), $this->createRelationResolver());
            $fields->relation('other_relation', T('TEST'), $this->createRelationResolver());
        }, 'TEST2');

        $fields = [
            'name' => true,
            'other_relation' => true,
            'count_other_relation' => true,
            '@TEST' => [ // on type
                'count_some_relation' => true,
                'some_relation' => [
                    'name' => true
                ]
            ],
            '@TEST2' => [ // on type
                'count_some_relation2' => true,
                'some_relation2' => [
                    'name' => true
                ]
            ]
        ];

        $resolveContext = $this->createResolveContext($type, $fields);

        $expectedFields = [
            'name' => true,
            'other_relation' => [],
            'count_other_relation' => true,
            'count_some_relation' => true,
            'some_relation' => [
                'name' => true
            ]
        ];

        $this->assertSame($expectedFields, $resolveContext->getRequestedFields());
        $this->assertEquals(['id', 'name'], $resolveContext->getSelectFields());

        $resolveContext = $this->createResolveContext($type2, $fields);

        $expectedFields = [
            'name' => true,
            'other_relation' => [],
            'count_other_relation' => true,
            'count_some_relation2' => true,
            'some_relation2' => [
                'name' => true
            ]
        ];

        $this->assertSame($expectedFields, $resolveContext->getRequestedFields());
        $this->assertEquals(['id', 'name'], $resolveContext->getSelectFields());
    }

    public function test_normalizes3()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
            $fields->attribute('name3', StringAttribute::class);
            $fields->relation('some_relation', T('TEST'), $this->createRelationResolver());
        });

        $type2 = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
            $fields->attribute('name2', StringAttribute::class);
        }, 'TEST2');

        $fields = [
            'name' => true,
            'name3' => true,
            'some_relation' => [
                'name' => true
            ],
            '@TEST2' => [
                'name2' => true
            ]
        ];

        $resolveContext = $this->createResolveContext($type, $fields);

        $expectedFields = [
            'name' => true,
            'name3' => true,
            'some_relation' => [
                'name' => true
            ]
        ];

        $this->assertSame($expectedFields, $resolveContext->getRequestedFields());
        $this->assertEquals(['id', 'name', 'name3'], $resolveContext->getSelectFields());

        $resolveContext = $this->createResolveContext($type2, $fields);

        $expectedFields = [
            'name' => true,
            'name2' => true
        ];

        $this->assertSame($expectedFields, $resolveContext->getRequestedFields());
        $this->assertEquals(['id', 'name', 'name2'], $resolveContext->getSelectFields());
    }

    public function test_normalizes_null()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
            $fields->relation('some_relation', T('TEST'));
        });

        $resolveContext = $this->createResolveContext($type, [
            'name' => null,
            'some_relation' => null,
            '@TEST' => [
                'name' => null,
                'some_relation' => null
            ]
        ]);

        $this->assertSame([], $resolveContext->getRequestedFields());
        $this->assertEquals(['id'], $resolveContext->getSelectFields());
    }

    public function test_normalizes_attributes()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
            $fields->attribute('name2', StringAttribute::class);
            $fields->attribute('name3', StringAttribute::class);
            $fields->attribute('name4', StringAttribute::class);
        });

        $resolveContext = $this->createResolveContext($type, [
            'name' => true,
            'name2' => false,
            'name3' => [],
            'name4' => null
        ]);

        $expectedFields = [
            'name' => true
        ];

        $this->assertSame($expectedFields, $resolveContext->getRequestedFields());
        $this->assertEquals(['id', 'name'], $resolveContext->getSelectFields());
    }

    public function test_normalizes_relations()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
            $fields->relation('some_relation', T('TEST'), $this->createRelationResolver());
            $fields->relation('relation2', T('TEST'), $this->createRelationResolver());
            $fields->relation('relation3', T('TEST'), $this->createRelationResolver());
        });

        $resolveContext = $this->createResolveContext($type, [
            'some_relation' => true,
            'relation2' => [],
            'relation3' => [
                'name' => true
            ]
        ]);

        $expectedFields = [
            'some_relation' => [],
            'relation2' => [],
            'relation3' => [
                'name' => true
            ]
        ];

        $this->assertSame($expectedFields, $resolveContext->getRequestedFields());
        $this->assertEquals(['id'], $resolveContext->getSelectFields());
    }

    public function test_normalizes_nested()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
            $fields->relation('some_relation', T('TEST'), $this->createRelationResolver());
        });

        $resolveContext = $this->createResolveContext($type, [
            'some_relation' => [
                'name' => true,
                'some_relation' => [
                    'name' => true,
                    'some_relation' => [
                        'name' => true,
                        '@TEST' => [
                            'name' => true
                        ]
                    ]
                ]
            ]
        ]);

        $expectedFields = [
            'some_relation' => [
                'name' => true,
                'some_relation' => [
                    'name' => true,
                    'some_relation' => [
                        'name' => true,
                        '@TEST' => [
                            'name' => true
                        ]
                    ]
                ]
            ]
        ];

        $this->assertSame($expectedFields, $resolveContext->getRequestedFields());
        $this->assertEquals(['id'], $resolveContext->getSelectFields());
    }

    public function test_normalizes_not_existing_fields()
    {
        $type = $this->createType(); // no fields

        $resolveContext = $this->createResolveContext($type, [
            'attribute_notexists' => true,
            'count_some_relation_notexists' => true,
            'relation_notexists' => [
                'attribute_notexists' => true,
                'relation_notexists' => true,
            ],
            '@TEST' => [ // on existing type
                'attribute_notexists' => true,
                'count_some_relation_notexists' => true,
                'relation_notexists' => true
            ],
            '@TEST_NOTEXISTS' => [
                'attribute_notexists' => true,
                'count_some_relation_notexists' => true,
                'relation_notexists' => true
            ]
        ]);

        $this->assertSame([], $resolveContext->getRequestedFields());
        $this->assertEquals(['id'], $resolveContext->getSelectFields());
    }

    public function test_get_nested()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
            $fields->relation('some_relation', T('TEST'), $this->createRelationResolver());
        });

        $resolveContext = $this->createResolveContext($type, [
            'some_relation' => [
                'name' => true,
                'some_relation' => true // not expanded just copied
            ]
        ]);

        $expectedFields = [
            'some_relation' => [
                'name' => true,
                'some_relation' => true
            ]
        ];

        $this->assertSame($expectedFields, $resolveContext->getRequestedFields());
        $this->assertEquals(['id'], $resolveContext->getSelectFields());
    }

    public function test_create_attribute_resolvers()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields
                ->attribute('name', function (StringAttribute $attribute) {
                    $attribute->resolve(function (QueryAttributeResolver $r) {
                        $this->testWatcher->called();
                        $r->get(function () {
                            $this->testWatcher->called();
                        });
                    });
                })
                ->attribute('title', function (StringAttribute $attribute) {
                    $attribute->resolve(function (QueryAttributeResolver $r) {
                        $this->testWatcher->called();
                        $r->get(function () {
                            $this->testWatcher->called();
                        });
                    });
                });
        });

        $resolveContext = $this->createResolveContext($type, [
            'name' => true
        ]);

        $this->assertEquals(0, $this->testWatcher->countCalls);

        $attributeResolvers = $resolveContext->getAttributeResolvers();
        $attributeResolvers = $resolveContext->getAttributeResolvers();
        $attributeResolvers = $resolveContext->getAttributeResolvers();

        $this->assertCount(1, $attributeResolvers);
        $this->assertEquals(['name'], array_keys($attributeResolvers));

        $this->assertEquals(1, $this->testWatcher->countCalls);

        $this->assertEquals(['id'], $resolveContext->getSelectFields());

        $this->assertEquals(1, $this->testWatcher->countCalls);

        $attributeResolvers['name']->resolve();

        $this->assertEquals(2, $this->testWatcher->countCalls);

        // ask for two attribues

        $resolveContext = $this->createResolveContext($type, [
            'name' => true,
            'title' => true
        ]);

        $attributeResolvers = $resolveContext->getAttributeResolvers();
        $attributeResolvers = $resolveContext->getAttributeResolvers();
        $attributeResolvers = $resolveContext->getAttributeResolvers();

        $this->assertCount(2, $attributeResolvers);
        $this->assertEquals(['name', 'title'], array_keys($attributeResolvers));

        $this->assertEquals(4, $this->testWatcher->countCalls);

        $this->assertEquals(['id'], $resolveContext->getSelectFields());

        $this->assertEquals(4, $this->testWatcher->countCalls);

        $attributeResolvers['name']->resolve();

        $this->assertEquals(5, $this->testWatcher->countCalls);

        $attributeResolvers['title']->resolve();

        $this->assertEquals(6, $this->testWatcher->countCalls);
    }

    public function test_create_relation_resolvers()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields
                ->relation('other', T('TYPE'), function (Relation $relation) {
                    $relation->resolve(function (QueryRelationResolver $r) {
                        $this->testWatcher->called();
                        $r->get(function () {
                            $this->testWatcher->called();
                            yield Model::fromSingle('TYPE', []);
                        });
                    });
                })
                ->relation('another', T('TYPE'), function (Relation $relation) {
                    $relation->resolve(function (QueryRelationResolver $r) {
                        $this->testWatcher->called();
                        $r->get(function () {
                            $this->testWatcher->called();
                            yield Model::fromSingle('TYPE', []);
                        });
                    });
                });
        });

        $resolveContext = $this->createResolveContext($type, [
            'other' => true
        ]);

        $this->assertEquals(0, $this->testWatcher->countCalls);

        $relationResolvers = $resolveContext->getRelationResolvers();
        $relationResolvers = $resolveContext->getRelationResolvers();
        $relationResolvers = $resolveContext->getRelationResolvers();

        $this->assertCount(1, $relationResolvers);
        $this->assertEquals(['other'], array_keys($relationResolvers));

        $this->assertEquals(1, $this->testWatcher->countCalls);

        $this->assertEquals(['id'], $resolveContext->getSelectFields());

        $this->assertEquals(1, $this->testWatcher->countCalls);

        $relationResolvers['other']->resolve();

        $this->assertEquals(2, $this->testWatcher->countCalls);

        // ask for two relations

        $resolveContext = $this->createResolveContext($type, [
            'other' => true,
            'another' => true
        ]);

        $relationResolvers = $resolveContext->getRelationResolvers();
        $relationResolvers = $resolveContext->getRelationResolvers();
        $relationResolvers = $resolveContext->getRelationResolvers();

        $this->assertCount(2, $relationResolvers);
        $this->assertEquals(['other', 'another'], array_keys($relationResolvers));

        $this->assertEquals(4, $this->testWatcher->countCalls);

        $this->assertEquals(['id'], $resolveContext->getSelectFields());

        $this->assertEquals(4, $this->testWatcher->countCalls);

        $relationResolvers['other']->resolve();

        $this->assertEquals(5, $this->testWatcher->countCalls);

        $relationResolvers['another']->resolve();

        $this->assertEquals(6, $this->testWatcher->countCalls);
    }

    public function test_owner_id_fields()
    {
        $type = $this->createType(function (FieldBag $fields) {
            $fields
                ->relation('other', T('TYPE'), function (Relation $relation) {
                    $relation->resolve(function (QueryRelationResolver $r) {
                        $r->ownerIdFields(['owner_other_id']);
                        $r->get(function () {
                            yield Model::fromSingle('TYPE', []);
                        });
                    });
                })
                ->relation('another', T('TYPE'), function (Relation $relation) {
                    $relation->resolve(function (QueryRelationResolver $r) {
                        $r->ownerIdFields(fn () => ['owner_another_id']);
                        $r->get(function () {
                            yield Model::fromSingle('TYPE', []);
                        });
                    });
                });
        });

        $resolveContext = $this->createResolveContext($type, [
            'other' => true,
            'another' => true
        ]);

        $this->assertEquals(['id', 'owner_other_id', 'owner_another_id'], $resolveContext->getSelectFields());
    }

    private function createType(?Closure $fieldsCallback = null, ?string $typeName = null): Type
    {
        $typeName ??= 'TEST';
        return $this->typeBuilder()->type($typeName, function (FieldBag $fields) use ($fieldsCallback) {
            if ($fieldsCallback) {
                $fieldsCallback($fields);
            }
        })->get(true);
    }

    private function createRelationResolver(): Closure
    {
        return function (Relation $relation) {
            $relation->resolve(function (QueryRelationResolver $r) {
            });
        };
    }

    private function createResolveContext(Type $type, array $fields): QueryResolveContext
    {
        return $this->container->create(QueryResolveContext::class)
            ->type($type)
            ->fields($fields);
    }
}
