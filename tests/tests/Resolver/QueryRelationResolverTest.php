<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Afeefa\ApiResources\Resolver\QueryRelationResolver;
use Afeefa\ApiResources\Test\QueryTest;

use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Type\Type;

use Closure;
use stdClass;

class QueryRelationResolverTest extends QueryTest
{
    public function test_simple()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners) {
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

    public function test_count()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->count(function (array $owners) {
                                foreach ($owners as $owner) {
                                    $owner->apiResourcesSetRelation('count_other', 1);
                                }
                            });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, [
            'title' => true,
            'count_other' => true
        ]);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'title0',
            'count_other' => 1
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_count_no_count_callback()
    {
        $this->expectException(MissingCallbackException::class);
        $this->expectExceptionMessage('Resolver for relation count_others needs to implement a count() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                        });
                    });
            }
        );

        $this->requestSingle($api, ['count_others' => true]);
    }

    public function test_count_map()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r
                                ->count(function (array $owners) {
                                    $objects = [];
                                    foreach ($owners as $index => $owner) {
                                        $objects['ownerId'] = $index + 1;
                                    }
                                    return $objects;
                                })
                                ->map(function (array $objects, Model $owner) {
                                    return $objects['ownerId'];
                                });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, [
            'title' => true,
            'count_other' => true
        ]);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'title0',
            'count_other' => 1
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    /**
     * @dataProvider countNoArrayOfIntegersDataprovider
     */
    public function test_single_count_map_no_array($returnValue)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Resolver for relation count_other needs to return an array of integers from its count() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($returnValue) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r
                                ->count(function (array $owners) use ($returnValue) {
                                    return $returnValue;
                                })
                                ->map(fn () => null);
                        });
                    });
            }
        );

        $this->requestSingle($api, [
            'count_other' => true
        ]);
    }

    public function countNoArrayOfIntegersDataprovider()
    {
        return [
            'return null' => [[null]],
            'return string' => [['string']],
            'return number' => [['0']],
            'return mixed' => [[0, '0', null, 'string']]
        ];
    }

    /**
     * @dataProvider countNoArrayOfIntegersDataprovider2
     */
    public function test_single_count_map_no_array2($returnValue)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Resolver for relation count_other needs to return an integer value from its map() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($returnValue) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r
                                ->count(function (array $owners) {
                                    return [1, 2, 3];
                                })
                                ->map(fn () => $returnValue);
                        });
                    });
            }
        );

        $this->requestSingle($api, [
            'count_other' => true
        ]);
    }

    public function countNoArrayOfIntegersDataprovider2()
    {
        return [
            'return null' => [null],
            'return string' => ['string'],
            'return number' => ['0']
        ];
    }

    public function test_count_map_yield()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r
                                ->count(function (array $owners) {
                                    foreach ($owners as $owner) {
                                        yield 'ownerId' => 2;
                                    }
                                })
                                ->map(function (array $objects, Model $owner) {
                                    return $objects['ownerId'];
                                });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, [
            'title' => true,
            'count_other' => true
        ]);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'title0',
            'count_other' => 2
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_count_list()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r
                                ->count(function (array $owners) {
                                    foreach ($owners as $index => $owner) {
                                        $owner->apiResourcesSetRelation('count_other', $index + 1);
                                    }
                                });
                        });
                    });
            },
            null,
            isList: true
        );

        $models = $this->requestList($api, [
            'title' => true,
            'count_other' => true
        ]);

        foreach ($models as $index => $model) {
            $expectedFields = [
                'type' => 'TYPE',
                'title' => 'title' . $index,
                'count_other' => $index + 1
            ];

            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    public function test_count_map_list()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r
                                ->count(function (array $owners) {
                                    $objects = [];
                                    foreach ($owners as $index => $owner) {
                                        $id = strval($index + 1);
                                        $owner->apiResourcesSetAttribute('id', $id);
                                        $objects[$id] = $index + 1;
                                    }
                                    return $objects;
                                })
                                ->map(function (array $objects, Model $owner) {
                                    return $objects[$owner->id];
                                });
                        });
                    });
            },
            null,
            isList: true
        );

        $models = $this->requestList($api, [
            'title' => true,
            'count_other' => true
        ]);

        foreach ($models as $index => $model) {
            $expectedFields = [
                'id' => strval($index + 1),
                'type' => 'TYPE',
                'title' => 'title' . $index,
                'count_other' => $index + 1
            ];

            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    public function test_count_map_list_yield()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r
                                ->count(function (array $owners) {
                                    foreach ($owners as $index => $owner) {
                                        $id = strval($index + 1);
                                        $owner->apiResourcesSetAttribute('id', $id);
                                        yield $id => $index + 1;
                                    }
                                })
                                ->map(function (array $objects, Model $owner) {
                                    return $objects[$owner->id];
                                });
                        });
                    });
            },
            null,
            isList: true
        );

        $models = $this->requestList($api, [
            'title' => true,
            'count_other' => true
        ]);

        foreach ($models as $index => $model) {
            $expectedFields = [
                'id' => strval($index + 1),
                'type' => 'TYPE',
                'title' => 'title' . $index,
                'count_other' => $index + 1
            ];

            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    /**
     * @dataProvider restrictToDataProvider
     */
    public function test_relation_restricted($restrictTo)
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($restrictTo) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) use ($restrictTo) {
                        $relation
                            ->restrictTo($restrictTo)
                            ->resolve(function (QueryRelationResolver $r) {
                                $r
                                    ->count(function (array $owners) {
                                        foreach ($owners as $index => $owner) {
                                            $owner->id = strval($index + 1);
                                            yield $owner->id => $index + 1;
                                        }
                                    })
                                    ->get(function (array $owners) {
                                        foreach ($owners as $index => $owner) {
                                            $owner->id = strval($index + 1);
                                            yield $owner->id => Model::fromSingle('TYPE', ['id' => 'fromOwner' . $owner->id]);
                                        }
                                    })
                                    ->map(function (array $objects, Model $owner) {
                                        return $objects[$owner->id];
                                    });
                            });
                    });
            },
            null,
            isList: true
        );

        $models = $this->requestList($api, [
            'other' => true,
            'count_other' => true
        ]);

        foreach ($models as $index => $model) {
            $id = strval($index + 1);
            $expectedFields = [
                'id' => $id,
                'type' => 'TYPE'
            ];

            if ($restrictTo !== Relation::RESTRICT_TO_GET) {
                $expectedFields['count_other'] = $index + 1;
            }

            if ($restrictTo !== Relation::RESTRICT_TO_COUNT) {
                $expectedFields['other'] = ['type' => 'TYPE', 'id' => 'fromOwner' . $id];
            }

            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    public function restrictToDataProvider()
    {
        return [
            'not restricted' => [null],
            'restricted to get' => [Relation::RESTRICT_TO_GET],
            'restricted to count' => [Relation::RESTRICT_TO_COUNT]
        ];
    }

    public function test_simple_yield()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners) {
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
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners) {
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
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners) {
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
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners, Closure $getSelectFields, Closure $getRequestedFields) use ($r) {
                                $this->testWatcher->called();
                                $this->testWatcher->selectFields($getSelectFields());
                                $this->testWatcher->requestedFields($getRequestedFields());

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
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners) use ($r) {
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
                    ->attribute('title', StringAttribute::class)
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners) use ($r) {
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
                                    yield $otherModels;
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

    public function test_list_nested_has_many_relation()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class)
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners) use ($r) {
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
                                    yield $otherModels;
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
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function () {
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
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners) use ($r) {
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
                    ->relation('different', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners) use ($r) {
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
                    ->attribute('title', StringAttribute::class)
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r
                                ->get(function (array $owners) {
                                    $objects = [];
                                    foreach ($owners as $index => $owner) {
                                        $otherModels = Model::fromList('TYPE', [
                                            ['title' => 'title' . 3 * $index],
                                            ['title' => 'title' . 3 * $index + 1],
                                            ['title' => 'title' . 3 * $index + 2]
                                        ]);
                                        $id = strval($index + 1);
                                        $owner->apiResourcesSetAttribute('id', $id);
                                        $owner->apiResourcesSetRelation('others', $otherModels);
                                        $objects[$id] = $otherModels;
                                    }
                                    return $objects;
                                })
                                ->map(function ($objects, Model $owner) {
                                    return $objects[$owner->id];
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
            'type' => 'TYPE', 'id' => '1', 'title' => 'title0', 'others' => [
                ['type' => 'TYPE', 'id' => '1', 'title' => 'title0', 'others' => [
                    ['type' => 'TYPE', 'title' => 'title0'],
                    ['type' => 'TYPE', 'title' => 'title1'],
                    ['type' => 'TYPE', 'title' => 'title2']
                ]],
                ['type' => 'TYPE', 'id' => '2', 'title' => 'title1', 'others' => [
                    ['type' => 'TYPE', 'title' => 'title3'],
                    ['type' => 'TYPE', 'title' => 'title4'],
                    ['type' => 'TYPE', 'title' => 'title5']
                ]],
                ['type' => 'TYPE', 'id' => '3', 'title' => 'title2', 'others' => [
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
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) {
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
        $this->expectExceptionMessage('Resolver for relation others needs to implement a get() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) {
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
        $this->expectExceptionMessage('Resolver for relation others needs to return an array from its get() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($returnValue) {
                $fields
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r->get(function () use ($returnValue) {
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
        $this->expectExceptionMessage('Resolver for relation other needs to return an array of ModelInterface objects from its get() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($returnValue) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r->get(fn () => $returnValue);
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
        $this->expectExceptionMessage('Resolver for relation others needs to return a nested array of ModelInterface objects from its get() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($returnValue) {
                $fields
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r->get(fn () => $returnValue);
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
                    ->relation('other', T('TYPE'), function (Relation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r
                                ->get(fn () => [])
                                ->map(fn () => $returnValue);
                        });
                    });
            }
        );

        $this->requestSingle($api, ['other' => true]);
    }

    public function test_single_map_returns_null()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r
                                ->get(fn () => [])
                                ->map(fn () => null);
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, ['other' => true]);

        $expectedFields = [
            'type' => 'TYPE',
            'other' => null
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
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
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r
                                ->get(fn () => [[]])
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
            'return mixed' => [[0, null, 'string']]
        ];
    }

    /**
     * @dataProvider loadArrayOfModelsDataprovider
     */
    public function test_get_list_returns_list_of_models($returnValue)
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($returnValue) {
                $fields
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) use ($returnValue) {
                        $relation->resolve(function (QueryRelationResolver $r) use ($returnValue) {
                            $r
                                ->get(fn () => $returnValue);
                        });
                    });
            }
        );

        $this->requestSingle($api, ['others' => true]);

        $this->assertTrue(true);
    }

    public function loadArrayOfModelsDataprovider()
    {
        return [
            'return empty' => [[]],
            'return empty' => [[[Model::fromSingle('TYPE')], [Model::fromSingle('TYPE')]]],
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
                    ->attribute('name', StringAttribute::class)
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners) use ($r) {
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
                    ->relation('other', [T('TYPE'), T('TYPE2')], function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function () use ($r) {
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
                    ->relation('other', $single ? T('TYPE') : [T('TYPE'), T('TYPE2')], function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function () use ($r) {
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
                    ->relation('other', [T('TYPE'), T('TYPE2')], function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function () use ($r) {
                                $r->getSelectFields();
                            });
                        });
                    });
            }
        );

        $this->request($api, ['other' => true]);
    }

    public function test_select_fields_union_missing_type2()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You need to pass a type name to getSelectFields() in the resolver of relation other since the relation returns an union type');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->relation('other', [T('TYPE'), T('TYPE2')], function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners, Closure $getSelectFields) {
                                $getSelectFields();
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
                    ->relation('other', $single ? T('TYPE') : [T('TYPE'), T('TYPE2')], function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function () use ($r) {
                                $r->getSelectFields('TYPE3');
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
    public function test_select_fields_wrong_type2($single)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The type name passed to getSelectFields() in the resolver of relation other is not supported by the relation');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) use ($single) {
                $fields
                    ->relation('other', $single ? T('TYPE') : [T('TYPE'), T('TYPE2')], function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->get(function (array $owners, Closure $getSelectFields) {
                                $getSelectFields('TYPE3');
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
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->ownerIdFields(['owner_other_id']);
                        });
                    })
                    ->relation('another', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->ownerIdFields(fn () => ['anowner_other_id']);
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) {
                $action
                    ->resolve(function (QueryActionResolver $r) {
                        $r->get(function () use ($r) {
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
                    ->attribute('title', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (TestRelationResolver $r) {
                            $r->get(function () use ($r) {
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
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (TestRelationResolver $r) {
                            $r->get(function () use ($r) {
                                $this->testWatcher->info($r->getResolveParam('key'));
                                return [];
                            });
                        }, ['key' => 'value']);
                    });
            }
        );

        $this->request($api, ['other' => true]);

        $this->assertEquals(['value'], $this->testWatcher->info);
    }

    public function test_request_params()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (TestRelationResolver $r) {
                            $r->get(function () use ($r) {
                                $this->testWatcher->info($r->getParams());
                                return [];
                            });
                        }, ['key' => 'value']);
                    });
            }
        );

        $this->request($api, [
            'other' => [
                '__params' => [
                    'a' => 'b'
                ]
            ]
        ]);

        $this->assertEquals([['a' => 'b']], $this->testWatcher->info);
    }

    protected function createApiWithTypeAndAction(Closure $fieldsCallback, $TypeClassOrClassesOrMeta = null, ?Closure $actionCallback = null, bool $isList = false): Api
    {
        $TypeClassOrClassesOrMeta ??= $isList ? fn () => Type::list(T('TYPE')) : fn () => T('TYPE');

        $actionCallback ??= function (Action $action) use ($isList) {
            $action
                ->resolve(function (QueryActionResolver $r) use ($isList) {
                    $r->get(function () use ($isList) {
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

        return parent::createApiWithTypeAndAction($fieldsCallback, $TypeClassOrClassesOrMeta, $actionCallback);
    }

    protected function request(Api $api, ?array $fields = null, ?array $params = null, ?array $filters = null)
    {
        $result = parent::request($api, $fields);
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
