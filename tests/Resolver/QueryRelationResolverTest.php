<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\HasManyRelation;
use Afeefa\ApiResources\Field\Fields\HasOneRelation;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Afeefa\ApiResources\Resolver\QueryRelationResolver;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Type\Type;

use Closure;
use stdClass;

class QueryRelationResolverTest extends ApiResourcesTest
{
    private TestWatcher $testWatcher;

    protected function setUp(): void
    {
        parent::setup();

        $this->testWatcher = new TestWatcher();
    }

    public function test_simple()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) {
                                $relatedModels = [];
                                foreach ($owners as $owner) {
                                    $this->testWatcher->called();
                                    $relatedModel = Model::fromSingle('TYPE', ['title' => 'title' . $this->testWatcher->countCalls]);
                                    $owner->apiResourcesSetRelation('other', $relatedModel);
                                    $relatedModels[] = $relatedModel;
                                }
                                return $relatedModels;
                            });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, [
            'title' => true,
            'other' => [
                'title' => true
            ]
        ]);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'title0',
            'other' => [
                'type' => 'TYPE',
                'title' => 'title1'
            ]
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_simple_yield()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) {
                                foreach ($owners as $owner) {
                                    $this->testWatcher->called();
                                    $relatedModel = Model::fromSingle('TYPE', ['title' => 'title' . $this->testWatcher->countCalls]);
                                    $owner->apiResourcesSetRelation('other', $relatedModel);
                                    yield $relatedModel;
                                }
                            });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, [
            'title' => true,
            'other' => [
                'title' => true
            ]
        ]);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'title0',
            'other' => [
                'type' => 'TYPE',
                'title' => 'title1'
            ]
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_list_simple()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) {
                                $this->testWatcher->called();
                                $relatedModels = [];
                                foreach ($owners as $owner) {
                                    $relatedModel = Model::fromSingle('TYPE', ['title' => 'title' . $this->testWatcher->countCalls]);
                                    $owner->apiResourcesSetRelation('other', $relatedModel);
                                    $relatedModels[] = $relatedModel;
                                }
                                return $relatedModels;
                            });
                        });
                    });
            },
            isList: true
        );

        $models = $this->requestList($api, [
            'title' => true,
            'other' => [
                'title' => true
            ]
        ]);

        foreach ($models as $index => $model) {
            $expectedFields = [
                'type' => 'TYPE',
                'title' => 'title' . $index,
                'other' => [
                    'type' => 'TYPE',
                    'title' => 'title1'
                ]
            ];

            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    public function test_list_simple_yield()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) {
                                $this->testWatcher->called();
                                foreach ($owners as $owner) {
                                    $relatedModel = Model::fromSingle('TYPE', ['title' => 'title' . $this->testWatcher->countCalls]);
                                    $owner->apiResourcesSetRelation('other', $relatedModel);
                                    yield $relatedModel;
                                }
                            });
                        });
                    });
            },
            isList: true
        );

        $models = $this->requestList($api, [
            'title' => true,
            'other' => [
                'title' => true
            ]
        ]);

        foreach ($models as $index => $model) {
            $expectedFields = [
                'type' => 'TYPE',
                'title' => 'title' . $index,
                'other' => [
                    'type' => 'TYPE',
                    'title' => 'title1'
                ]
            ];

            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    public function test_nested_has_one_relation()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) use ($r) {
                                $this->testWatcher->called();
                                $this->testWatcher->selectFields($r->getSelectFields());
                                $this->testWatcher->requestedFields($r->getRequestedFieldNames());

                                foreach ($owners as $owner) {
                                    $relatedModel = Model::fromSingle('TYPE', ['title' => 'title' . $this->testWatcher->countCalls]);
                                    $owner->apiResourcesSetRelation('other', $relatedModel);
                                    yield $relatedModel;
                                }
                            });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, [
            'title' => true,
            'other' => [
                'title' => true,
                'other' => [
                    'title' => true,
                    'other' => [
                        'title' => true
                    ]
                ]
            ]
        ]);

        $this->assertEquals(3, $this->testWatcher->countCalls);
        $this->assertEquals([['id', 'title'], ['id', 'title'], ['id', 'title']], $this->testWatcher->selectFields);
        $this->assertEquals([['title', 'other'], ['title', 'other'], ['title']], $this->testWatcher->requestedFields);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'title0',
            'other' => [
                'type' => 'TYPE',
                'title' => 'title1',
                'other' => [
                    'type' => 'TYPE',
                    'title' => 'title2',
                    'other' => [
                        'type' => 'TYPE',
                        'title' => 'title3',
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_list_with_nested_has_one_relation()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) use ($r) {
                                $this->testWatcher->called();
                                $this->testWatcher->selectFields($r->getSelectFields());
                                $this->testWatcher->requestedFields($r->getRequestedFieldNames());

                                $relatedModels = [];
                                foreach ($owners as $owner) {
                                    $relatedModel = Model::fromSingle('TYPE', ['title' => 'title' . $this->testWatcher->countCalls]);
                                    $owner->apiResourcesSetRelation('other', $relatedModel);
                                    $relatedModels[] = $relatedModel;
                                }
                                return $relatedModels;
                            });
                        });
                    });
            },
            isList: true
        );

        $models = $this->requestList($api, [
            'title' => true,
            'other' => [
                'title' => true,
                'other' => [
                    'title' => true,
                    'other' => [
                        'title' => true
                    ]
                ]
            ]
        ]);

        $this->assertCount(5, $models);

        $this->assertEquals(3, $this->testWatcher->countCalls);
        $this->assertEquals([['id', 'title'], ['id', 'title'], ['id', 'title']], $this->testWatcher->selectFields);
        $this->assertEquals([['title', 'other'], ['title', 'other'], ['title']], $this->testWatcher->requestedFields);

        foreach ($models as $index => $model) {
            $expectedFields = [
                'type' => 'TYPE',
                'title' => 'title' . $index,
                'other' => [
                    'type' => 'TYPE',
                    'title' => 'title1',
                    'other' => [
                        'type' => 'TYPE',
                        'title' => 'title2',
                        'other' => [
                            'type' => 'TYPE',
                            'title' => 'title3',
                        ]
                    ]
                ]
            ];

            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    public function test_nested_has_many_relation()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('others', Type::list(T('TYPE')), function (HasManyRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) use ($r) {
                                $this->testWatcher->called();
                                $this->testWatcher->selectFields($r->getSelectFields());
                                $this->testWatcher->requestedFields($r->getRequestedFieldNames());

                                foreach ($owners as $index => $owner) {
                                    $otherModels = Model::fromList('TYPE', [
                                        ['title' => 'title' . 3 * $index],
                                        ['title' => 'title' . 3 * $index + 1],
                                        ['title' => 'title' . 3 * $index + 2]
                                    ]);
                                    $owner->apiResourcesSetRelation('others', $otherModels);
                                    yield from $otherModels;
                                }
                            });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, [
            'title' => true,
            'others' => [
                'title' => true,
                'others' => [
                    'title' => true
                ]
            ]
        ]);

        $this->assertEquals(2, $this->testWatcher->countCalls);
        $this->assertEquals([['id', 'title'], ['id', 'title']], $this->testWatcher->selectFields);
        $this->assertEquals([['title', 'others'], ['title']], $this->testWatcher->requestedFields);

        $expectedFields = [
            'type' => 'TYPE', 'title' => 'title0', 'others' => [
                ['type' => 'TYPE', 'title' => 'title0', 'others' => [
                    ['type' => 'TYPE', 'title' => 'title0'],
                    ['type' => 'TYPE', 'title' => 'title1'],
                    ['type' => 'TYPE', 'title' => 'title2']
                ]],
                ['type' => 'TYPE', 'title' => 'title1', 'others' => [
                    ['type' => 'TYPE', 'title' => 'title3'],
                    ['type' => 'TYPE', 'title' => 'title4'],
                    ['type' => 'TYPE', 'title' => 'title5']
                ]],
                ['type' => 'TYPE', 'title' => 'title2', 'others' => [
                    ['type' => 'TYPE', 'title' => 'title6'],
                    ['type' => 'TYPE', 'title' => 'title7'],
                    ['type' => 'TYPE', 'title' => 'title8']
                ]]
            ]
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_list_with_nested_has_many_relation()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('others', Type::list(T('TYPE')), function (HasManyRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) use ($r) {
                                $this->testWatcher->called();
                                $this->testWatcher->selectFields($r->getSelectFields());
                                $this->testWatcher->requestedFields($r->getRequestedFieldNames());

                                foreach ($owners as $index => $owner) {
                                    $otherModels = Model::fromList('TYPE', [
                                        ['title' => 'title' . 3 * $index],
                                        ['title' => 'title' . 3 * $index + 1],
                                        ['title' => 'title' . 3 * $index + 2]
                                    ]);
                                    $owner->apiResourcesSetRelation('others', $otherModels);
                                    yield from $otherModels;
                                }
                            });
                        });
                    });
            },
            isList: true
        );

        $models = $this->requestList($api, [
            'title' => true,
            'others' => [
                'title' => true,
                'others' => [
                    'title' => true
                ]
            ]
        ]);

        $this->assertCount(5, $models);

        $this->assertEquals(2, $this->testWatcher->countCalls);
        $this->assertEquals([['id', 'title'], ['id', 'title']], $this->testWatcher->selectFields);
        $this->assertEquals([['title', 'others'], ['title']], $this->testWatcher->requestedFields);

        foreach ($models as $index => $model) {
            $expectedFields = [
                'type' => 'TYPE', 'title' => 'title' . $index, 'others' => [ // 0, 1, 2
                    ['type' => 'TYPE', 'title' => 'title' . (3 * $index), 'others' => [ // 0, 3, 6
                        ['type' => 'TYPE', 'title' => 'title' . (9 * $index)], // 0, 9, 18
                        ['type' => 'TYPE', 'title' => 'title' . (9 * $index + 1)],
                        ['type' => 'TYPE', 'title' => 'title' . (9 * $index + 2)]
                    ]],
                    ['type' => 'TYPE', 'title' => 'title' . (3 * $index + 1), 'others' => [
                        ['type' => 'TYPE', 'title' => 'title' . (9 * $index + 3)],
                        ['type' => 'TYPE', 'title' => 'title' . (9 * $index + 4)],
                        ['type' => 'TYPE', 'title' => 'title' . (9 * $index + 5)]
                    ]],
                    ['type' => 'TYPE', 'title' => 'title' . (3 * $index + 2), 'others' => [ // 2, 5, 8
                        ['type' => 'TYPE', 'title' => 'title' . (9 * $index + 6)], // 6, 15, 24
                        ['type' => 'TYPE', 'title' => 'title' . (9 * $index + 7)],
                        ['type' => 'TYPE', 'title' => 'title' . (9 * $index + 8)]
                    ]]
                ]
            ];

            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    public function test_relation_not_requested()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function () {
                                $this->testWatcher->called();
                            });
                        });
                    });
            }
        );

        $this->assertEquals(0, $this->testWatcher->countCalls);

        $model = $this->requestSingle($api);

        $this->assertEquals(0, $this->testWatcher->countCalls);

        $this->assertEquals(['type' => 'TYPE'], $model->jsonSerialize());
    }

    public function test_creates_different_resolvers()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) use ($r) {
                                $this->testWatcher->called();
                                $this->testWatcher->info('other');
                                $this->testWatcher->info($r->getRelation()->getName());
                                $this->testWatcher->selectFields($r->getSelectFields());
                                $this->testWatcher->requestedFields($r->getRequestedFieldNames());

                                $models = [];
                                foreach ($owners as $owner) {
                                    $model = Model::fromSingle('TYPE', ['title' => 'title' . $this->testWatcher->countCalls]);
                                    $owner->apiResourcesSetRelation('other', $model);
                                    $models[] = $model;
                                }
                                return $models;
                            });
                        });
                    })
                    ->relation('different', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) use ($r) {
                                $this->testWatcher->called();
                                $this->testWatcher->info('different');
                                $this->testWatcher->info($r->getRelation()->getName());
                                $this->testWatcher->selectFields($r->getSelectFields());
                                $this->testWatcher->requestedFields($r->getRequestedFieldNames());

                                $models = [];
                                foreach ($owners as $owner) {
                                    $model = Model::fromSingle('TYPE', ['title' => 'title' . $this->testWatcher->countCalls]);
                                    $owner->apiResourcesSetRelation('different', $model);
                                    $models[] = $model;
                                }
                                return $models;
                            });
                        });
                    });
            },
            isList: true
        );

        $models = $this->requestList($api, [
            'title' => true,
            'other' => [
                'title' => true,
                'other' => [
                    'title' => true,
                ],
                'different' => [
                    'title' => true,
                ]
            ],
            'different' => [
                'title' => true,
                'other' => [
                    'title' => true,
                ],
                'different' => [
                    'title' => true,
                ]
            ]
        ]);

        $this->assertCount(5, $models);

        $this->assertEquals(6, $this->testWatcher->countCalls);

        $this->assertEquals([
            'other', 'other', 'other', 'other', 'different', 'different',
            'different', 'different', 'other', 'other', 'different', 'different'
        ], $this->testWatcher->info);

        $this->assertEquals([['id', 'title'], ['id', 'title'], ['id', 'title'], ['id', 'title'], ['id', 'title'], ['id', 'title']], $this->testWatcher->selectFields);
        $this->assertEquals([
            ['title', 'other', 'different'],
            ['title'],
            ['title'],
            ['title', 'other', 'different'],
            ['title'],
            ['title']
        ], $this->testWatcher->requestedFields);

        foreach ($models as $index => $model) {
            $expectedFields = [
                'type' => 'TYPE',
                'title' => 'title' . $index,
                'other' => [
                    'type' => 'TYPE',
                    'title' => 'title1',
                    'other' => [
                        'type' => 'TYPE',
                        'title' => 'title2'
                    ],
                    'different' => [
                        'type' => 'TYPE',
                        'title' => 'title3'
                    ]
                ],
                'different' => [
                    'type' => 'TYPE',
                    'title' => 'title4',
                    'other' => [
                        'type' => 'TYPE',
                        'title' => 'title5'
                    ],
                    'different' => [
                        'type' => 'TYPE',
                        'title' => 'title6'
                    ]
                ]
            ];

            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    public function test_resolver_with_map()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('others', Type::list(T('TYPE')), function (HasManyRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r
                                ->load(function (array $owners) {
                                    $objects = [];
                                    foreach ($owners as $index => $owner) {
                                        $otherModels = Model::fromList('TYPE', [
                                            ['title' => 'title' . 3 * $index],
                                            ['title' => 'title' . 3 * $index + 1],
                                            ['title' => 'title' . 3 * $index + 2]
                                        ]);
                                        $owner->apiResourcesSetRelation('others', $otherModels);
                                        $objects[] = [
                                            'owner' => $owner,
                                            'models' => $otherModels
                                        ];
                                    }
                                    return $objects;
                                })
                                ->map(function ($objects, Model $owner) {
                                    foreach ($objects as $object) {
                                        if ($object['owner'] === $owner) {
                                            return $object['models'];
                                        }
                                    }
                                });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, [
            'title' => true,
            'others' => [
                'title' => true,
                'others' => [
                    'title' => true
                ]
            ]
        ]);

        $expectedFields = [
            'type' => 'TYPE', 'title' => 'title0', 'others' => [
                ['type' => 'TYPE', 'title' => 'title0', 'others' => [
                    ['type' => 'TYPE', 'title' => 'title0'],
                    ['type' => 'TYPE', 'title' => 'title1'],
                    ['type' => 'TYPE', 'title' => 'title2']
                ]],
                ['type' => 'TYPE', 'title' => 'title1', 'others' => [
                    ['type' => 'TYPE', 'title' => 'title3'],
                    ['type' => 'TYPE', 'title' => 'title4'],
                    ['type' => 'TYPE', 'title' => 'title5']
                ]],
                ['type' => 'TYPE', 'title' => 'title2', 'others' => [
                    ['type' => 'TYPE', 'title' => 'title6'],
                    ['type' => 'TYPE', 'title' => 'title7'],
                    ['type' => 'TYPE', 'title' => 'title8']
                ]]
            ]
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_no_attribute_resolver_argument()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Resolve callback for relation others on type TYPE must receive a QueryRelationResolver as argument.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->relation('others', Type::list(T('TYPE')), function (HasManyRelation $relation) {
                        $relation->resolve(function () {
                        });
                    });
            }
        );

        $this->requestSingle($api, ['others' => true]);
    }

    public function test_no_load_callback()
    {
        $this->expectException(MissingCallbackException::class);
        $this->expectExceptionMessage('Resolver for relation others needs to implement a load() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->relation('others', Type::list(T('TYPE')), function (HasManyRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                        });
                    });
            }
        );

        $this->requestSingle($api, ['others' => true]);
    }

    /**
     * @dataProvider loadNoArrayDataprovider
     */
    public function test_load_returns_no_array($returnValue)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Resolver for relation others needs to return an array from its load() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($returnValue) {
                $fields
                    ->relation('others', Type::list(T('TYPE')), function (HasManyRelation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r->load(function () use ($returnValue) {
                                if ($returnValue !== 'nothing') {
                                    return $returnValue;
                                }
                            });
                        });
                    });
            }
        );

        $this->requestSingle($api, ['others' => true]);
    }

    public function loadNoArrayDataprovider()
    {
        return [
            'return null' => [null],
            'return string' => ['string'],
            'return number' => [0],
            'return nothing' => ['nothing']
        ];
    }

    /**
     * @dataProvider loadNoArrayOfModelsDataprovider
     */
    public function test_single_load_returns_no_list_of_models($returnValue)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Resolver for relation other needs to return an array of ModelInterface objects from its load() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($returnValue) {
                $fields
                    ->relation('other', T('TYPE'), function (HasManyRelation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r->load(fn () => $returnValue);
                        });
                    });
            }
        );

        $this->requestSingle($api, ['other' => true]);
    }

    /**
     * @dataProvider loadNoArrayOfModelsDataprovider
     */
    public function test_list_load_returns_no_list_of_models($returnValue)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Resolver for relation others needs to return an array of ModelInterface objects from its load() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($returnValue) {
                $fields
                    ->relation('others', Type::list(T('TYPE')), function (HasManyRelation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r->load(fn () => $returnValue);
                        });
                    });
            }
        );

        $this->requestSingle($api, ['others' => true]);
    }

    /**
     * @dataProvider mapNoModelDataprovider
     */
    public function test_single_map_returns_no_model($returnValue)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Resolver for relation other needs to return a ModelInterface object or null from its map() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($returnValue) {
                $fields
                    ->relation('other', T('TYPE'), function (HasManyRelation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r
                                ->load(fn () => [])
                                ->map(fn () => $returnValue);
                        });
                    });
            }
        );

        $this->requestSingle($api, ['other' => true]);
    }

    /**
     * @dataProvider loadNoArrayOfModelsDataprovider
     */
    public function test_list_map_returns_no_list_of_models($returnValue)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Resolver for relation others needs to return an array of ModelInterface objects from its map() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($returnValue) {
                $fields
                    ->relation('others', Type::list(T('TYPE')), function (HasManyRelation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r
                                ->load(fn () => [])
                                ->map(fn () => $returnValue);
                        });
                    });
            }
        );

        $this->requestSingle($api, ['others' => true]);
    }

    public function mapNoModelDataprovider()
    {
        return [
            'return null' => [null],
            'return string' => ['string'],
            'return number' => [0],
            'return array' => [[]],
            'return array with models' => [[Model::fromSingle('TYPE', [])]],
            'return array' => [new stdClass()]
        ];
    }

    public function loadNoArrayOfModelsDataprovider()
    {
        return [
            'return null' => [[null]],
            'return string' => [['string']],
            'return number' => [[0]],
            'return number' => [[0, null, 'string']]
        ];
    }

    /**
     * @dataProvider requestFieldsDataProvider
     */
    public function test_requested_fields($fields, $expectedFields)
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) use ($r) {
                                foreach ($owners as $owner) {
                                    $attributes = [];
                                    foreach ($r->getSelectFields() as $selectField) {
                                        $attributes[$selectField] = $selectField;
                                        unset($attributes['id']);
                                    }

                                    $relatedModel = Model::fromSingle('TYPE', $attributes);
                                    $owner->apiResourcesSetRelation('other', $relatedModel);
                                    yield $relatedModel;
                                }
                            });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, [
            'title' => true,
            'other' => $fields
        ]);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'title0',
            'other' => array_merge([
                'type' => 'TYPE'
            ], array_combine($expectedFields, $expectedFields))
        ];

        if ($fields === null) {
            unset($expectedFields['other']);
        }

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function requestFieldsDataProvider()
    {
        // [request fields, calculated fields]
        return [
            'name' => [['name' => true], ['name']],

            'title' => [['title' => true], ['title']],

            'name+title' => [[
                'name' => true,
                'title' => true
            ], ['name', 'title']],

            'name+title+unknown' => [[
                'name' => true,
                'title' => true,
                'unknown' => true
            ], ['name', 'title']],

            'nothing' => [true, []],

            'nothing' => [null, []],

            'empty' => [[], []],

            'unknown_relation' => [[
                'relation' => [
                    'field' => true
                ]
            ], []],

            'name+unknown' => [[
                'name' => true,
                'relation' => [],
                'unknown' => true
            ], ['name']]
        ];
    }

    public function test_requested_fields_union_missing_type()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You need to pass a type name to getRequestedFields() in the resolver of relation other since the relation returns an union type');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->relation('other', [T('TYPE'), T('TYPE2')], function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function () use ($r) {
                                $r->getRequestedFields();
                            });
                        });
                    });
            }
        );

        $this->request($api, ['other' => true]);
    }

    /**
     * @dataProvider wrongTypeNameToSelectFieldsDataProvider
     */
    public function test_requested_fields_wrong_type($single)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The type name passed to getRequestedFields() in the resolver of relation other is not supported by the relation');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($single) {
                $fields
                    ->relation('other', $single ? T('TYPE') : [T('TYPE'), T('TYPE2')], function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function () use ($r) {
                                $r->getRequestedFields('TYPE3');
                            });
                        });
                    });
            }
        );

        $this->request($api, ['other' => true]);
    }

    public function test_select_fields_union_missing_type()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You need to pass a type name to getSelectFields() in the resolver of relation other since the relation returns an union type');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->relation('other', [T('TYPE'), T('TYPE2')], function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function () use ($r) {
                                $r->getSelectFields();
                            });
                        });
                    });
            }
        );

        $this->request($api, ['other' => true]);
    }

    /**
     * @dataProvider wrongTypeNameToSelectFieldsDataProvider
     */
    public function test_select_fields_wrong_type($single)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The type name passed to getSelectFields() in the resolver of relation other is not supported by the relation');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($single) {
                $fields
                    ->relation('other', $single ? T('TYPE') : [T('TYPE'), T('TYPE2')], function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function () use ($r) {
                                $r->getSelectFields('TYPE3');
                            });
                        });
                    });
            }
        );

        $this->request($api, ['other' => true]);
    }

    public function wrongTypeNameToSelectFieldsDataProvider()
    {
        return [
            'single' => [true],
            'list' => [false]
        ];
    }

    public function test_owner_id_fields()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->ownerIdFields(['owner_other_id']);
                        });
                    })
                    ->relation('another', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->ownerIdFields(fn () => ['anowner_other_id']);
                        });
                    });
            },
            function (Action $action) {
                $action
                    ->response(T('TYPE'))
                    ->resolve(function (QueryActionResolver $r) {
                        $r->load(function () use ($r) {
                            $this->testWatcher->selectFields($r->getSelectFields());
                        });
                    });
            }
        );

        $this->requestSingle($api, [
            'title' => true,
            'other' => true,
            'another' => true
        ]);

        $this->assertEquals([['id', 'title', 'owner_other_id', 'anowner_other_id']], $this->testWatcher->selectFields);
    }

    public function test_calls_requested_fields()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (TestRelationResolver $r) {
                            $r->load(function () use ($r) {
                                $this->testWatcher->info($r->countCalculateCalls);

                                $r->getRequestedFields();
                                $r->fieldIsRequested('test');
                                $r->fieldIsRequested('test2');
                                $r->fieldIsRequested('test3');
                                $r->getRequestedFieldNames();

                                $this->testWatcher->info($r->countCalculateCalls);

                                return [];
                            });
                        });
                    });
            }
        );

        $this->request($api, ['other' => true]);

        $this->assertEquals([0, 1], $this->testWatcher->info);
    }

    public function test_resolve_params()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (TestRelationResolver $r) {
                            $r->load(function () use ($r) {
                                $this->testWatcher->info($r->getResolveParams());
                                $this->testWatcher->info($r->getResolveParam('key'));
                                return [];
                            });
                        }, ['key' => 'value']);
                    });
            }
        );

        $this->request($api, ['other' => true]);

        $this->assertEquals([['key' => 'value'], 'value'], $this->testWatcher->info);
    }

    private function createApiWithTypeAndAction(Closure $fieldsCallback, ?Closure $actionCallback = null, bool $isList = false): Api
    {
        $actionCallback ??= function (Action $action) use ($isList) {
            $response = $isList ? Type::list(T('TYPE')) : T('TYPE');
            $action
                ->response($response)
                ->resolve(function (QueryActionResolver $r) use ($isList) {
                    $r->load(function () use ($isList) {
                        if ($isList) {
                            return Model::fromList('TYPE', [
                                ['title' => 'title0'],
                                ['title' => 'title1'],
                                ['title' => 'title2'],
                                ['title' => 'title3'],
                                ['title' => 'title4'] // 5 models
                            ]);
                        }
                        return Model::fromSingle('TYPE', ['title' => 'title0']);
                    });
                });
        };

        return $this->apiBuilder()->api('API', function (Closure $addResource, Closure $addType) use ($fieldsCallback, $actionCallback) {
            $addType('TYPE', $fieldsCallback);
            $addResource('RES', function (Closure $addAction) use ($actionCallback) {
                $addAction('ACT', $actionCallback);
            });
        })->get();
    }

    private function request(Api $api, ?array $fields = null)
    {
        $result = $api->request(function (ApiRequest $request) use ($fields) {
            $request
                ->resourceType('RES')
                ->actionName('ACT');

            if ($fields) {
                $request->fields($fields);
            }
        });
        return $result['data'];
    }

    private function requestSingle(Api $api, ?array $fields = null): ?Model
    {
        return $this->request($api, $fields);
    }

    /**
     * @return Model[]
     */
    private function requestList(Api $api, ?array $fields = null): array
    {
        return $this->request($api, $fields);
    }
}

class TestRelationResolver extends QueryRelationResolver
{
    public int $countCalculateCalls = 0;

    protected function calculateRequestedFields(?string $typeName = null): array
    {
        $this->countCalculateCalls++;
        return parent::calculateRequestedFields($typeName);
    }
}
