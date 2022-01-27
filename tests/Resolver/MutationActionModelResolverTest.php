<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\MutationActionModelResolver;
use Afeefa\ApiResources\Resolver\MutationRelationHasOneResolver;
use Afeefa\ApiResources\Test\MutationRelationTest;

use function Afeefa\ApiResources\Test\T;

use stdClass;

class MutationActionModelResolverTest extends MutationRelationTest
{
    private $should_update = false;

    /**
     * @dataProvider missingCallbacksDataProvider
     */
    public function test_missing_callbacks($missingCallback)
    {
        $this->expectException(MissingCallbackException::class);
        $n = in_array($missingCallback, ['add', 'update']) ? 'n' : '';
        $this->expectExceptionMessage("Resolver for action ACT on resource RES needs to implement a{$n} {$missingCallback}() method.");

        $api = $this->createApiWithAction(
            function (Action $action) use ($missingCallback) {
                $action
                    ->input(T('TYPE'))
                    ->response(T('TYPE'))
                    ->resolve(function (MutationActionModelResolver $r) use ($missingCallback) {
                        if ($missingCallback !== 'get') {
                            $r->get(fn () => null);
                        }
                        if ($missingCallback !== 'add') {
                            $r->add(fn () => null);
                        }
                        if ($missingCallback !== 'update') {
                            $r->update(fn () => null);
                        }
                        if ($missingCallback !== 'delete') {
                            $r->delete(fn () => null);
                        }
                    });
            }
        );

        $this->request($api, data: ['other' => []]);
    }

    public function missingCallbacksDataProvider()
    {
        return [
            ['get'],
            ['add'],
            ['update'],
            ['delete']
        ];
    }

    public function test_with_all_callbacks()
    {
        $api = $this->createApiWithAction(
            function (Action $action) {
                $action
                    ->input(T('TYPE'))
                    ->response(T('TYPE'))
                    ->resolve(function (MutationActionModelResolver $r) {
                        $r
                            ->get(fn () => [])
                            ->add(fn () => Model::fromSingle('TYPE', []))
                            ->update(fn () => null)
                            ->delete(fn () => null);
                    });
            }
        );

        $this->request($api);

        $this->assertTrue(true);
    }

    /**
     * @dataProvider mutationDataProvider
     */
    public function test_mutation($update, $fields, $expectedInfo, $expectedFields)
    {
        if ($update) {
            $this->should_update = true;
        }

        $api = $this->createApiWithUpdateTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class);
            },
            function (Action $action) {
                $action
                    ->input(T('TYPE'))
                    ->response(T('TYPE'))
                    ->resolve(function (MutationActionModelResolver $r) {
                        $r
                            ->get(function () {
                                $this->testWatcher->info('get');
                                if ($this->should_update) {
                                    return Model::fromSingle('TYPE');
                                }
                            })
                            ->add(function (string $typeName, array $saveFields) use ($r) {
                                $this->testWatcher->info('add');
                                $this->testWatcher->saveFields($saveFields);
                                return Model::fromSingle('TYPE');
                            })
                            ->update(function (ModelInterface $model, array $saveFields) use ($r) {
                                $this->testWatcher->info('update');
                                $this->testWatcher->saveFields($saveFields);
                            })
                            ->delete(fn () => null);
                    });
            }
        );

        $this->request(
            $api,
            params: $update ? ['id' => '123'] : [],
            data: $fields
        );

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedFields, $this->testWatcher->saveFields);
    }

    public function mutationDataProvider()
    {
        // [data fields, save fields]
        return [
            'with_id' => [
                true,
                ['name' => 'hase'],
                ['get', 'update'],
                [['name' => 'hase']]
            ],

            'without_id' => [
                false,
                ['name' => 'hase'],
                ['add'],
                [['name' => 'hase']]
            ]
        ];
    }

    /**
     * @dataProvider saveFieldsDataProvider
     */
    public function test_save_fields($fields, $expectedFields)
    {
        $api = $this->createApiWithUpdateTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->attribute('title', StringAttribute::class);
            },
            function (Action $action) {
                $action
                    ->input(T('TYPE'))
                    ->response(T('TYPE'))
                    ->resolve(function (MutationActionModelResolver $r) {
                        $r
                            ->get(fn () => null)
                            ->update(fn () => null)
                            ->add(function (string $typeName, array $saveFields) use ($r) {
                                $this->testWatcher->saveFields($saveFields);
                                return Model::fromSingle('TYPE');
                            })
                            ->delete(fn () => null);
                    });
            }
        );

        $this->request(
            $api,
            data: $fields
        );

        $this->assertEquals([$expectedFields], $this->testWatcher->saveFields);
    }

    public function saveFieldsDataProvider()
    {
        // [data fields, save fields]
        return [
            'name' => [
                ['name' => 'name1'],
                ['name' => 'name1']
            ],

            'title' => [
                ['title' => 'title1'],
                ['title' => 'title1']
            ],

            'name+title' => [
                [
                    'name' => 'name1',
                    'title' => 'title1'
                ],
                [
                    'name' => 'name1',
                    'title' => 'title1'
                ]
            ],

            'name+title+unknown' => [
                [
                    'name' => 'name1',
                    'title' => 'title1',
                    'unknown' => 'unknown'
                ],
                [
                    'name' => 'name1',
                    'title' => 'title1'
                ]
            ],

            'empty' => [[], []],

            'unknown_relation' => [
                ['relation' => ['field' => true]],
                []
            ]
        ];
    }

    /**
     * @dataProvider createSaveRelationsDataProvider
     */
    public function test_create_save_fields_relations($fields, $expectedFields, $expectedInfo)
    {
        $api = $this->createApiWithUpdateTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->attribute('title', StringAttribute::class)
                    ->relation('relation', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(function () {
                                    $this->testWatcher->info('relation_get');
                                })
                                ->add(function ($owner, $typeName, $saveFields) {
                                    $this->testWatcher->info('relation_add');
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle('TYPE');
                                })
                                ->update(fn () => null)
                                ->delete(function () {
                                    $this->testWatcher->info('relation_delete');
                                });
                        });
                    })
                    ->relation('relation_before', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->saveRelatedToOwner(function (?string $id, ?string $typeName) {
                                    return [
                                        'related_id' => $id,
                                        'related_type' => $typeName
                                    ];
                                })
                                ->addBeforeOwner(function ($type, $saveFields) {
                                    $this->testWatcher->info('relation_before_add');
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle('TYPE', ['id' => '10']);
                                })
                                ->get(fn () => null)
                                ->add(fn () => Model::fromSingle('TYPE'))
                                ->update(fn () => null)
                                ->delete(function () {
                                    $this->testWatcher->info('relation_before_delete');
                                });
                        });
                    })
                    ->relation('relation_after', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->saveOwnerToRelated(function (string $id, string $typeName) {
                                    return [
                                        'owner_id' => $id,
                                        'owner_type' => $typeName
                                    ];
                                })
                                ->get(function () {
                                    $this->testWatcher->info('relation_after_get');
                                })
                                ->add(function ($owner, $type, $saveFields) use ($r) {
                                    $this->testWatcher->info('relation_after_add');
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle('TYPE');
                                })
                                ->update(fn () => null)
                                ->delete(function () {
                                    $this->testWatcher->info('relation_after_delete');
                                });
                        });
                    });
            },
            function (Action $action) {
                $action
                    ->input(T('TYPE'))
                    ->response(T('TYPE'))
                    ->resolve(function (MutationActionModelResolver $r) {
                        $r
                            ->get(fn () => null)
                            ->update(fn () => null)
                            ->add(function (string $typeName, array $saveFields) use ($r) {
                                $this->testWatcher->saveFields($saveFields);
                                $this->testWatcher->info('owner');
                                return Model::fromSingle('TYPE', ['id' => '3']);
                            })
                            ->delete(fn () => null);
                    });
            }
        );

        $this->request(
            $api,
            data: $fields
        );

        $this->assertEquals($expectedFields, $this->testWatcher->saveFields);

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
    }

    public function createSaveRelationsDataProvider()
    {
        // [requested fields, get relation, expected save fields, resolve order]
        $data = [
            'name' => [
                ['name' => 'name1', 'relation' => ['name' => 'name2']],
                [
                    ['name' => 'name1'],
                    ['name' => 'name2']
                ],
                ['owner', 'relation_add']
            ],

            'before_owner' => [
                ['name' => 'name1', 'relation_before' => ['name' => 'name2']],
                [
                    ['name' => 'name2'],
                    ['name' => 'name1', 'related_id' => '10', 'related_type' => 'TYPE']
                ],
                ['relation_before_add', 'owner']
            ],

            'before_owner_null' => [
                ['name' => 'name1', 'relation_before' => null],
                [
                    ['name' => 'name1', 'related_id' => null, 'related_type' => null]
                ],
                ['owner']
            ],

            'after_owner' => [
                ['name' => 'name1', 'relation_after' => ['name' => 'name3']],
                [
                    ['name' => 'name1'],
                    ['owner_id' => '3', 'owner_type' => 'TYPE', 'name' => 'name3']
                ],
                ['owner', 'relation_after_add']
            ],

            'after_owner_null' => [
                ['name' => 'name1', 'relation_after' => null],
                [
                    ['name' => 'name1']
                ],
                ['owner']
            ],

            'all' => [
                [
                    'name' => 'name1',
                    'relation' => ['name' => 'name2'],
                    'relation_before' => ['name' => 'name2'],
                    'relation_after' => ['name' => 'name3']
                ],
                [
                    ['name' => 'name2'], // relation before
                    ['name' => 'name1', 'related_id' => '10', 'related_type' => 'TYPE'], // owner
                    ['name' => 'name2'], // relation
                    ['owner_id' => '3', 'owner_type' => 'TYPE', 'name' => 'name3'], // relation after
                ],
                ['relation_before_add', 'owner', 'relation_add', 'relation_after_add']
            ],

            'all_null' => [
                [
                    'name' => 'name1',
                    'relation' => null,
                    'relation_before' => null,
                    'relation_after' => null
                ],
                [
                    ['name' => 'name1', 'related_id' => null, 'related_type' => null] // owner
                ],
                ['owner']
            ],
        ];

        return $data;
    }

    /**
     * @dataProvider updateSaveRelationsDataProvider
     */
    public function test_update_save_fields_relations($fields, $expectedFields, $expectedInfo)
    {
        $api = $this->createApiWithUpdateTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->attribute('title', StringAttribute::class)
                    ->relation('relation', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(function () {
                                    $this->testWatcher->info('relation_get');
                                })
                                ->add(function ($owner, $type, $saveFields) {
                                    $this->testWatcher->info('relation_add');
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle('TYPE');
                                })
                                ->update(function () {
                                    $this->testWatcher->info('relation_update');
                                })
                                ->delete(function () {
                                    $this->testWatcher->info('relation_delete');
                                });
                        });
                    })
                    ->relation('relation_before', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->saveRelatedToOwner(function (?string $id, ?string $typeName) {
                                    return [
                                        'related_id' => $id,
                                        'related_type' => $typeName
                                    ];
                                })
                                ->addBeforeOwner(function () {
                                    $this->testWatcher->info('relation_before_add_before_owner');
                                    return Model::fromSingle('TYPE', ['id' => '10']);
                                })
                                ->get(function () {
                                    $this->testWatcher->info('relation_before_get');
                                })
                                ->add(function ($owner, $type, $saveFields) {
                                    $this->testWatcher->info('relation_before_add');
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle('TYPE', ['id' => '10']);
                                })
                                ->update(function () {
                                    $this->testWatcher->info('relation_before_update');
                                })
                                ->delete(function () {
                                    $this->testWatcher->info('relation_before_delete');
                                });
                        });
                    })
                    ->relation('relation_after', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->saveOwnerToRelated(function (string $id, string $typeName) {
                                    return [
                                        'owner_id' => $id,
                                        'owner_type' => $typeName
                                    ];
                                })
                                ->get(function () {
                                    $this->testWatcher->info('relation_after_get');
                                })
                                ->add(function ($owner, $type, $saveFields) {
                                    $this->testWatcher->info('relation_after_add');
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle('TYPE');
                                })
                                ->update(function () {
                                    $this->testWatcher->info('relation_after_update');
                                })
                                ->delete(function () {
                                    $this->testWatcher->info('relation_after_delete');
                                });
                        });
                    });
            },
            function (Action $action) {
                $action
                    ->input(T('TYPE'))
                    ->response(T('TYPE'))
                    ->resolve(function (MutationActionModelResolver $r) {
                        $r
                            ->add(fn () => null)
                            ->update(function (ModelInterface $model, array $saveFields) {
                                $this->testWatcher->saveFields($saveFields);
                                $this->testWatcher->info('owner');
                            })
                            ->get(function () {
                                return Model::fromSingle('TYPE', ['id' => '3']);
                            })
                            ->delete(fn () => null);
                    });
            }
        );

        $this->request(
            $api,
            data: $fields,
            params: ['id' => '3']
        );

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedFields, $this->testWatcher->saveFields);
    }

    public function updateSaveRelationsDataProvider()
    {
        // [requested fields, get relation, expected save fields, resolve order]
        $data = [
            'name' => [
                ['name' => 'name1', 'relation' => ['name' => 'name2']],
                [
                    ['name' => 'name1'],
                    ['name' => 'name2']
                ],
                ['owner', 'relation_get', 'relation_add']
            ],

            'before_owner' => [
                ['name' => 'name1', 'relation_before' => ['name' => 'name2']],
                [
                    ['name' => 'name2'],
                    ['name' => 'name1', 'related_id' => '10', 'related_type' => 'TYPE']
                ],
                ['relation_before_get', 'relation_before_add', 'owner']
            ],

            'before_owner_null' => [
                ['name' => 'name1', 'relation_before' => null],
                [
                    ['name' => 'name1', 'related_id' => null, 'related_type' => null]
                ],
                ['relation_before_get', 'owner']
            ],

            'after_owner' => [
                ['name' => 'name1', 'relation_after' => ['name' => 'name3']],
                [
                    ['name' => 'name1'],
                    ['owner_id' => '3', 'owner_type' => 'TYPE', 'name' => 'name3']
                ],
                ['owner', 'relation_after_get', 'relation_after_add']
            ],

            'after_owner_null' => [
                ['name' => 'name1', 'relation_after' => null],
                [
                    ['name' => 'name1']
                ],
                ['owner', 'relation_after_get']
            ],

            'all' => [
                [
                    'name' => 'name1',
                    'relation' => ['name' => 'name2'],
                    'relation_before' => ['name' => 'name2'],
                    'relation_after' => ['name' => 'name3']
                ],
                [
                    ['name' => 'name2'], // relation before
                    ['name' => 'name1', 'related_id' => '10', 'related_type' => 'TYPE'], // owner
                    ['name' => 'name2'], // relation
                    ['owner_id' => '3', 'owner_type' => 'TYPE', 'name' => 'name3'], // relation after
                ],
                ['relation_before_get', 'relation_before_add', 'owner', 'relation_get', 'relation_add', 'relation_after_get', 'relation_after_add']
            ],

            'all_null' => [
                [
                    'name' => 'name1',
                    'relation' => null,
                    'relation_before' => null,
                    'relation_after' => null
                ],
                [
                    ['name' => 'name1', 'related_id' => null, 'related_type' => null] // owner
                ],
                ['relation_before_get', 'owner', 'relation_get', 'relation_after_get']
            ],
        ];

        return $data;
    }

    /**
     * @dataProvider addDoesNotReturnModelDataProvider
     */
    public function test_add_does_not_return_model($return)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Add callback of mutation resolver for action ACT on resource RES must return a ModelInterface object.');

        $api = $this->createApiWithAction(
            function (Action $action) use ($return) {
                $action
                    ->input(T('TYPE'))
                    ->response(T('TYPE'))
                    ->resolve(function (MutationActionModelResolver $r) use ($return) {
                        $r
                            ->get(fn () => null)
                            ->update(fn () => null)
                            ->add(function () use ($return) {
                                if ($return !== 'NOTHING') {
                                    return $return;
                                }
                            })
                            ->delete(fn () => null);
                    });
            }
        );

        $this->request($api);
    }

    public function addDoesNotReturnModelDataProvider()
    {
        return [
            'null' => [null],
            'array' => [[]],
            'string' => ['string'],
            'object' => [new stdClass()],
            'nothing' => ['NOTHING']
        ];
    }

    /**
     * @dataProvider getDoesNotReturnModelDataProvider
     */
    public function test_get_does_not_return_model_or_null($return)
    {
        if (in_array($return, [null, 'NOTHING'], true)) {
            $this->assertTrue(true);
        } else {
            $this->expectException(InvalidConfigurationException::class);
            $this->expectExceptionMessage('Get callback of mutation resolver for action ACT on resource RES must return a ModelInterface object or null.');
        }

        $api = $this->createApiWithAction(
            function (Action $action) use ($return) {
                $action
                    ->input(T('TYPE'))
                    ->response(T('TYPE'))
                    ->resolve(function (MutationActionModelResolver $r) use ($return) {
                        $r
                            ->get(function () use ($return) {
                                if ($return !== 'NOTHING') {
                                    return $return;
                                }
                            })
                            ->add(fn () => Model::fromSingle('TYPE'))
                            ->update(fn () => null)
                            ->delete(fn () => null);
                    });
            }
        );

        $this->request($api, params: ['id' => '10']);
    }

    public function getDoesNotReturnModelDataProvider()
    {
        return [
            'null' => [null],
            'array' => [[]],
            'string' => ['string'],
            'object' => [new stdClass()],
            'nothing' => ['NOTHING']
        ];
    }

    /**
     * @dataProvider deleteDataProvider
     */
    public function test_delete($fields, $expectedInfo, $expectedInfo2)
    {
        $api = $this->createApiWithUpdateTypeAndAction(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class);
            },
            function (Action $action) {
                $action
                    ->input(T('TYPE'))
                    ->response(T('TYPE'))
                    ->resolve(function (MutationActionModelResolver $r) {
                        $r
                            ->get(function (string $id, string $typeName) {
                                $this->testWatcher->info('get');
                                return Model::fromSingle($typeName, ['id' => $id]);
                            })
                            ->add(function () {
                                $this->testWatcher->info('add');
                            })
                            ->update(function () {
                                $this->testWatcher->info('update');
                            })
                            ->delete(function (ModelInterface $modelToDelete) {
                                $this->testWatcher->info('delete');

                                $this->testWatcher->info2([
                                    $modelToDelete->apiResourcesGetId(),
                                    $modelToDelete->apiResourcesGetType()
                                ]);
                            });
                    });
            }
        );

        $this->request(
            $api,
            params: ['id' => '123'],
            data: $fields
        );

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedInfo2, $this->testWatcher->info2);
    }

    public function deleteDataProvider()
    {
        // $fields, $expectedInfo, $expectedInfo2
        return [
            'with_id' => [
                null,
                ['get', 'delete'],
                [['123', 'TYPE']]
            ]
        ];
    }
}
