<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\HasManyRelation;
use Afeefa\ApiResources\Field\Fields\HasOneRelation;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Afeefa\ApiResources\Resolver\QueryAttributeResolver;
use Afeefa\ApiResources\Resolver\QueryRelationResolver;
use Afeefa\ApiResources\Test\QueryTest;

use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Type\Type;

use Closure;

class QueryAttributeResolverTest extends QueryTest
{
    public function test_simple_resolver()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->load(function (array $owners) {
                                $this->testWatcher->called();

                                foreach ($owners as $owner) {
                                    $owner->apiResourcesSetAttribute('title', 'calculatedTitle');
                                }
                            });
                        });
                    });
            }
        );

        $this->assertEquals(0, $this->testWatcher->countCalls);

        $model = $this->requestSingle($api, ['title' => true]);

        $this->assertEquals(1, $this->testWatcher->countCalls);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'calculatedTitle'
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_simple_resolver_with_list()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->load(function (array $owners) {
                                $this->testWatcher->called();

                                foreach ($owners as $index => $owner) {
                                    $owner->apiResourcesSetAttribute('title', 'calculatedTitle' . $index);
                                }
                            });
                        });
                    });
            },
            isList: true,
        );

        $this->assertEquals(0, $this->testWatcher->countCalls);

        $models = $this->requestList($api, ['title' => true]);

        $this->assertCount(5, $models);
        $this->assertEquals(1, $this->testWatcher->countCalls);

        foreach ($models as $index => $model) {
            $expectedFields = [
                'type' => 'TYPE',
                'title' => 'calculatedTitle' . $index
            ];
            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    public function test_attribute_not_requested()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
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
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->load(function () use ($r) {
                                $this->testWatcher->called();
                                $this->testWatcher->info('title');
                                $this->testWatcher->info($r->getAttribute()->getName());
                            });
                        });
                    })
                    ->attribute('summary', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->load(function () use ($r) {
                                $this->testWatcher->called();
                                $this->testWatcher->info('summary');
                                $this->testWatcher->info($r->getAttribute()->getName());
                            });
                        });
                    });
            }
        );

        $this->assertEquals(0, $this->testWatcher->countCalls);

        $this->requestSingle($api, [
            'title' => true,
            'summary' => true
        ]);

        $this->assertEquals(2, $this->testWatcher->countCalls);
        $this->assertEquals(['title', 'title', 'summary', 'summary'], $this->testWatcher->info);
    }

    public function test_multiple_attributes()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->load(function (array $owners) {
                                $owners[0]->apiResourcesSetAttribute('title', 'calculatedTitle');
                            });
                        });
                    })
                    ->attribute('summary', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->load(function (array $owners) {
                                $owners[0]->apiResourcesSetAttribute('summary', 'calculatedSummary');
                            });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, [
            'title' => true,
            'summary' => true
        ]);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'calculatedTitle',
            'summary' => 'calculatedSummary'
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_multiple_attributes_with_list()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->load(function (array $owners) {
                                foreach ($owners as $index => $owner) {
                                    $owner->apiResourcesSetAttribute('title', 'calculatedTitle' . $index);
                                }
                            });
                        });
                    })
                    ->attribute('summary', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->load(function (array $owners) {
                                foreach ($owners as $index => $owner) {
                                    $owner->apiResourcesSetAttribute('summary', 'calculatedSummary' . $index);
                                }
                            });
                        });
                    });
            },
            isList: true
        );

        $models = $this->requestList($api, [
            'title' => true,
            'summary' => true
        ]);

        foreach ($models as $index => $model) {
            $expectedFields = [
                'type' => 'TYPE',
                'title' => 'calculatedTitle' . $index,
                'summary' => 'calculatedSummary' . $index
            ];

            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    public function test_simple_resolver_with_map()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r
                                ->load(function () {
                                    return ['calculatedTitle'];
                                })
                                ->map(function (array $fieldValues) {
                                    return $fieldValues[0];
                                });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, ['title' => true]);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'calculatedTitle'
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_resolver_with_map_and_list()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r
                                ->load(function (array $owners) {
                                    $objects = [];
                                    foreach ($owners as $index => $owner) {
                                        $objects[] = ['owner' => $owner, 'title' => 'calculatedTitle' . $index];
                                    }
                                    return $objects;
                                })
                                ->map(function (array $objects, Model $owner) {
                                    foreach ($objects as $object) {
                                        if ($object['owner'] === $owner) {
                                            return $object['title'];
                                        }
                                    }
                                });
                        });
                    })
                    ->attribute('summary', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r
                                ->load(function (array $owners) {
                                    $objects = [];
                                    foreach ($owners as $index => $owner) {
                                        $objects[] = ['owner' => $owner, 'summary' => 'calculatedSummary' . $index];
                                    }
                                    return $objects;
                                })
                                ->map(function (array $objects, Model $owner) {
                                    foreach ($objects as $object) {
                                        if ($object['owner'] === $owner) {
                                            return $object['summary'];
                                        }
                                    }
                                });
                        });
                    });
            },
            isList: true
        );

        $models = $this->requestList($api, [
            'title' => true,
            'summary' => true
        ]);

        foreach ($models as $index => $model) {
            $expectedFields = [
                'type' => 'TYPE',
                'title' => 'calculatedTitle' . $index,
                'summary' => 'calculatedSummary' . $index
            ];

            $this->assertEquals($expectedFields, $model->jsonSerialize());
        }
    }

    public function test_nested_has_one_relation()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->load(function (array $owners) {
                                $owners[0]->apiResourcesSetAttribute('title', 'calculatedTitle' . $this->testWatcher->countCalls);

                                $this->testWatcher->called();
                            });
                        });
                    })
                    ->relation('other', T('TYPE'), function (HasOneRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) {
                                $relatedModels = [];
                                foreach ($owners as $owner) {
                                    $relatedModel = Model::fromSingle('TYPE', []);
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
                'title' => true,
                'other' => [
                    'title' => true,
                    'other' => [
                        'title' => true
                    ]
                ]
            ]
        ]);

        $this->assertEquals(4, $this->testWatcher->countCalls);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'calculatedTitle0',
            'other' => [
                'type' => 'TYPE',
                'title' => 'calculatedTitle1',
                'other' => [
                    'type' => 'TYPE',
                    'title' => 'calculatedTitle2',
                    'other' => [
                        'type' => 'TYPE',
                        'title' => 'calculatedTitle3',
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_nested_has_many_relation()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->load(function (array $owners) {
                                $this->testWatcher->called();

                                foreach ($owners as $index => $owner) {
                                    $owner->apiResourcesSetAttribute('title', 'calculatedTitle' . $index);
                                }
                            });
                        });
                    })
                    ->relation('others', Type::list(T('TYPE')), function (HasManyRelation $relation) {
                        $relation->resolve(function (QueryRelationResolver $r) {
                            $r->load(function (array $owners) {
                                $relatedModels = [];
                                foreach ($owners as $owner) {
                                    $otherModels = Model::fromList('TYPE', [[], [], []]);
                                    $owner->apiResourcesSetRelation('others', $otherModels);
                                    $relatedModels = [...$relatedModels, ...$otherModels];
                                }
                                return $relatedModels;
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

        $this->assertEquals(3, $this->testWatcher->countCalls);

        $expectedFields = [
            'type' => 'TYPE', 'title' => 'calculatedTitle0', 'others' => [
                ['type' => 'TYPE', 'title' => 'calculatedTitle0', 'others' => [
                    ['type' => 'TYPE', 'title' => 'calculatedTitle0'],
                    ['type' => 'TYPE', 'title' => 'calculatedTitle1'],
                    ['type' => 'TYPE', 'title' => 'calculatedTitle2']
                ]],
                ['type' => 'TYPE', 'title' => 'calculatedTitle1', 'others' => [
                    ['type' => 'TYPE', 'title' => 'calculatedTitle3'],
                    ['type' => 'TYPE', 'title' => 'calculatedTitle4'],
                    ['type' => 'TYPE', 'title' => 'calculatedTitle5']
                ]],
                ['type' => 'TYPE', 'title' => 'calculatedTitle2', 'others' => [
                    ['type' => 'TYPE', 'title' => 'calculatedTitle6'],
                    ['type' => 'TYPE', 'title' => 'calculatedTitle7'],
                    ['type' => 'TYPE', 'title' => 'calculatedTitle8']
                ]]
            ]
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_no_attribute_resolver_argument()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Resolve callback for attribute title on type TYPE must receive a QueryAttributeResolver as argument.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function () {
                        });
                    });
            }
        );

        $this->requestSingle($api, ['title' => true]);
    }

    public function test_no_load_callback()
    {
        $this->expectException(MissingCallbackException::class);
        $this->expectExceptionMessage('Resolver for attribute title needs to implement a load() method.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                        });
                    });
            }
        );

        $this->requestSingle($api, ['title' => true]);
    }

    public function test_no_load_callback_but_select_fields()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->select('something');
                        });
                    });
            }
        );

        $this->requestSingle($api, ['title' => true]);

        $this->assertTrue(true);
    }

    public function test_load_returns_no_array_if_map_used()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Resolver for attribute title needs to return an array if map() is used.');

        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r
                                ->load(function () {
                                })
                                ->map(function () {
                                });
                        });
                    });
            }
        );

        $this->requestSingle($api, ['title' => true]);
    }

    public function test_select_fields()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->select('something', function ($model) {
                                $this->testWatcher->selectFields($model->selectFields);

                                return 'calculatedTitle';
                            });
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, ['title' => true]);

        $this->assertEquals(['id', 'something'], $model->selectFields);
        $this->assertEquals([['id', 'something']], $this->testWatcher->selectFields);

        $expectedFields = [
            'type' => 'TYPE',
            'title' => 'calculatedTitle'
        ];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    public function test_select_fields_without_callback()
    {
        $api = $this->createApiWithTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->resolve(function (QueryAttributeResolver $r) {
                            $r->select('something');
                        });
                    });
            }
        );

        $model = $this->requestSingle($api, ['title' => true]);

        $this->assertEquals(['id', 'something'], $model->selectFields);

        $expectedFields = ['type' => 'TYPE'];

        $this->assertEquals($expectedFields, $model->jsonSerialize());
    }

    protected function createApiWithTypeAndAction(Closure $fieldsCallback, ?Closure $actionCallback = null, bool $isList = false): Api
    {
        $actionCallback ??= function (Action $action) use ($isList) {
            $response = $isList ? Type::list(T('TYPE')) : T('TYPE');
            $action
                ->response($response)
                ->resolve(function (QueryActionResolver $r) use ($isList) {
                    $r->load(function () use ($isList, $r) {
                        $m = function () use ($r) {
                            return TestModel::fromSingle('TYPE', [])
                                ->selectFields($r->getSelectFields());
                        };

                        if ($isList) {
                            return [
                                $m(), $m(), $m(), $m(), $m() // 5 models
                            ];
                        }
                        return $m();
                    });
                });
        };

        return parent::createApiWithTypeAndAction($fieldsCallback, $actionCallback);
    }

    protected function request(Api $api, ?array $fields = null, ?array $params = null, ?array $filters = null)
    {
        $result = parent::request($api, $fields);
        return $result['data'];
    }

    private function requestSingle(Api $api, ?array $fields = null): Model
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
