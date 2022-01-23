<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\MutationRelationHasOneResolver;
use Afeefa\ApiResources\Test\MutationRelationTest;
use function Afeefa\ApiResources\Test\T;

use stdClass;

class MutationRelationHasOneResolverTest extends MutationRelationTest
{
    /**
     * @dataProvider missingCallbacksDataProvider
     */
    public function test_missing_callbacks($missingCallback)
    {
        $this->expectException(MissingCallbackException::class);
        $n = in_array($missingCallback, ['add', 'update']) ? 'n' : '';
        $this->expectExceptionMessage("Resolver for relation other needs to implement a{$n} {$missingCallback}() method.");

        $api = $this->createApiWithType(
            function (FieldBag $fields) use ($missingCallback) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) use ($missingCallback) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) use ($missingCallback) {
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
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(fn () => [])
                                ->add(fn () => Model::fromSingle('TYPE'))
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            }
        );

        $this->request($api, data: ['other' => []]);

        $this->assertTrue(true);
    }

    /**
     * @dataProvider saveToOwnerMissingCallbacksDataProvider
     */
    public function test_save_to_owner_missing_callbacks($missingCallback)
    {
        $this->expectException(MissingCallbackException::class);
        $n = in_array($missingCallback, ['add', 'addBeforeOwner', 'update']) ? 'n' : '';
        $this->expectExceptionMessage("Resolver for relation other needs to implement a{$n} {$missingCallback}() method.");

        $api = $this->createApiWithType(
            function (FieldBag $fields) use ($missingCallback) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) use ($missingCallback) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) use ($missingCallback) {
                            $r->saveRelatedToOwner(fn () => null);

                            if ($missingCallback !== 'get') {
                                $r->get(fn () => null);
                            }
                            if ($missingCallback !== 'addBeforeOwner') {
                                $r->addBeforeOwner(fn () => null);
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
                    });
            }
        );

        $this->request($api, data: ['other' => []]);

        $this->assertTrue(true);
    }

    public function saveToOwnerMissingCallbacksDataProvider()
    {
        return [
            ['get'],
            ['addBeforeOwner'],
            ['add'],
            ['update'],
            ['delete']
        ];
    }

    private $update_owner_existingData = [];

    /**
     * @dataProvider updateOwnerDataProvider
     */
    public function test_update_owner($existingData, $data, $expectedInfo, $expectedInfo2, $expectedSaveFields)
    {
        $this->update_owner_existingData = $existingData;

        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(function (ModelInterface $owner) use ($r) {
                                    $this->testWatcher->info('get');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $r->getRelation()->getName()
                                    ]);

                                    if ($this->update_owner_existingData) {
                                        return Model::fromSingle('TYPE', $this->update_owner_existingData);
                                    }
                                    return null;
                                })
                                ->add(function (ModelInterface $owner, string $typeName, array $saveFields) use ($r) {
                                    $this->testWatcher->info('add');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);

                                    $this->testWatcher->saveFields($saveFields);

                                    return Model::fromSingle('TYPE');
                                })
                                ->update(function (ModelInterface $owner, ModelInterface $modelToUpdate, array $saveFields) use ($r) {
                                    $this->testWatcher->info('update');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $modelToUpdate->apiResourcesGetId(),
                                        $modelToUpdate->apiResourcesGetType(),
                                        $r->getRelation()->getName()
                                    ]);

                                    $this->testWatcher->saveFields($saveFields);
                                })
                                ->delete(function (ModelInterface $owner, ModelInterface $modelToDelete) use ($r) {
                                    $this->testWatcher->info('delete');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $modelToDelete->apiResourcesGetId(),
                                        $modelToDelete->apiResourcesGetType(),
                                        $r->getRelation()->getName()
                                    ]);
                                });
                        });
                    });
            }
        );

        $this->request($api, data: ['other' => $data], params: ['id' => '111333']);

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedInfo2, $this->testWatcher->info2);
        $this->assertEquals($expectedSaveFields, $this->testWatcher->saveFields);
    }

    public function updateOwnerDataProvider()
    {
        // $existingData, $data, $expectedInfo, $expectedInfo2, $expectedSaveFields
        return [
            'new_null' => [
                [],
                null,
                ['get'],
                [['111333', 'TYPE', 'other']],
                []
            ],

            'new_empty_data' => [
                [],
                [],
                ['get', 'add'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', 'TYPE', 'other']
                ],
                [[]]
            ],

            'new_unknown_field' => [
                [],
                ['a' => 'b'],
                ['get', 'add'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', 'TYPE', 'other']
                ],
                [[]]
            ],

            'new_valid_field_no_id' => [
                [],
                ['name' => 'name1'],
                ['get', 'add'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', 'TYPE', 'other']
                ],
                [['name' => 'name1']]
            ],

            'new_valid_field_with_id' => [
                [],
                ['id' => '4', 'name' => 'name1'],
                ['get', 'add'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', 'TYPE', 'other']
                ],
                [['name' => 'name1']]
            ],

            'existing_null' => [
                ['id' => '10'],
                null,
                ['get', 'delete'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other']
                ],
                []
            ],

            'existing_empty_data' => [
                ['id' => '10'],
                [],
                ['get', 'update'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                ],
                [[]]
            ],

            'existing_unknown_field' => [
                ['id' => '10'],
                ['a' => 'b'],
                ['get', 'update'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                ],
                [[]]
            ],

            'existing_valid_field_no_id' => [
                ['id' => '10'],
                ['name' => 'name1'],
                ['get', 'update'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                ],
                [['name' => 'name1']]
            ],

            'existing_valid_field_with_id' => [
                ['id' => '10'],
                ['id' => '4', 'name' => 'name1'],
                ['get', 'update'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other']
                ],
                [['name' => 'name1']]
            ],

            'existing_valid_field_same_id' => [
                ['id' => '4'],
                ['id' => '4', 'name' => 'name1'],
                ['get', 'update'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '4', 'TYPE', 'other']
                ],
                [['name' => 'name1']]
            ]
        ];
    }

    /**
     * @dataProvider createOwnerDataProvider
     */
    public function test_create_owner($data, $expectedInfo, $expectedInfo2, $expectedSaveFields)
    {
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(function () {
                                    $this->testWatcher->info('get'); // never called
                                })
                                ->add(function (ModelInterface $owner, string $typeName, array $saveFields) use ($r) {
                                    $this->testWatcher->info('add');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);

                                    $this->testWatcher->saveFields($saveFields);

                                    return Model::fromSingle('TYPE');
                                })
                                ->update(function () { // never called
                                    $this->testWatcher->info('update');
                                })
                                ->delete(function () { // never called
                                    $this->testWatcher->info('delete');
                                });
                        });
                    });
            }
        );

        $this->request($api, data: ['other' => $data]);

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedInfo2, $this->testWatcher->info2);
        $this->assertEquals($expectedSaveFields, $this->testWatcher->saveFields);
    }

    public function createOwnerDataProvider()
    {
        // $data, $expectedInfo, $expectedInfo2, $expectedSaveFields
        return [
            'null' => [
                null,
                [],
                [],
                []
            ],

            'empty_data' => [
                [],
                ['add'],
                [['111333', 'TYPE', 'TYPE', 'other']],
                [[]]
            ],

            'unknown_field' => [
                ['a' => 'b'],
                ['add'],
                [['111333', 'TYPE', 'TYPE', 'other']],
                [[]]
            ],

            'valid_field_no_id' => [
                ['name' => 'name1'],
                ['add'],
                [['111333', 'TYPE', 'TYPE', 'other']],
                [['name' => 'name1']]
            ],

            'valid_field_with_id' => [
                ['id' => '4', 'name' => 'name1'],
                ['add'],
                [['111333', 'TYPE', 'TYPE', 'other']],
                [['name' => 'name1']]
            ]
        ];
    }

    /**
     * @dataProvider saveToOwnerCreateDataProvider
     */
    public function test_save_to_owner_create_owner($data, $expectedInfo, $expectedInfo2, $expectedSaveFields)
    {
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->saveRelatedToOwner(function (?string $id, ?string $typeName) use ($r) {
                                    $this->testWatcher->info('save_to_owner');

                                    $this->testWatcher->info2([
                                        $id,
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);

                                    return [
                                        'related_id' => $id,
                                        'related_type' => $typeName
                                    ];
                                })
                                ->addBeforeOwner(function (string $typeName, array $saveFields) use ($r) {
                                    $this->testWatcher->info('add_before_owner');

                                    $this->testWatcher->info2([
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);

                                    $this->testWatcher->saveFields($saveFields);

                                    return Model::fromSingle($typeName, ['id' => '101010']);
                                })

                                // never called
                                ->get(function () {
                                    $this->testWatcher->info('get');
                                })
                                ->add(function () {
                                    $this->testWatcher->info('add');
                                })
                                ->update(function () {
                                    $this->testWatcher->info('update');
                                })
                                ->delete(function () {
                                    $this->testWatcher->info('delete');
                                });
                        });
                    });
            }
        );

        $this->request($api, data: ['other' => $data]);

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedInfo2, $this->testWatcher->info2);
        $this->assertEquals($expectedSaveFields, $this->testWatcher->saveFields);
    }

    public function saveToOwnerCreateDataProvider()
    {
        // $data, $expectedInfo, $expectedInfo2, $expectedSaveFields
        return [
            'null' => [
                null,
                ['save_to_owner'],
                [[null, null, 'other']],
                []
            ],

            'empty_data' => [
                [],
                ['add_before_owner', 'save_to_owner'],
                [
                    ['TYPE', 'other'],
                    ['101010', 'TYPE', 'other']
                ],
                [[]]
            ],

            'unknown_field' => [
                ['a' => 'b'],
                ['add_before_owner', 'save_to_owner'],
                [
                    ['TYPE', 'other'],
                    ['101010', 'TYPE', 'other']
                ],
                [[]]
            ],

            'no_id' => [
                ['name' => 'name1'],
                ['add_before_owner', 'save_to_owner'],
                [
                    ['TYPE', 'other'],
                    ['101010', 'TYPE', 'other']
                ],
                [['name' => 'name1']]
            ],

            'id' => [
                ['id' => '4', 'name' => 'name1'],
                ['add_before_owner', 'save_to_owner'],
                [
                    ['TYPE', 'other'],
                    ['101010', 'TYPE', 'other']
                ],
                [['name' => 'name1']]
            ]
        ];
    }

    private $save_to_owner_update_existingData = [];

    /**
     * @dataProvider saveToOwnerUpdateDataProvider
     */
    public function test_save_to_owner_update_owner($existingData, $data, $expectedInfo, $expectedInfo2, $expectedSaveFields)
    {
        $this->save_to_owner_update_existingData = $existingData;

        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->saveRelatedToOwner(function (?string $id, ?string $typeName) use ($r) {
                                    $this->testWatcher->info('save_to_owner');

                                    $this->testWatcher->info2([
                                        $id,
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);

                                    return [
                                        'related_id' => $id,
                                        'related_type' => $typeName
                                    ];
                                })
                                ->get(function (ModelInterface $owner) use ($r) {
                                    $this->testWatcher->info('get');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $r->getRelation()->getName()
                                    ]);

                                    if ($this->save_to_owner_update_existingData) {
                                        return Model::fromSingle('TYPE', $this->save_to_owner_update_existingData);
                                    }
                                    return null;
                                })
                                ->add(function (ModelInterface $owner, string $typeName, array $saveFields) use ($r) {
                                    $this->testWatcher->info('add');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);

                                    $this->testWatcher->saveFields($saveFields);

                                    return Model::fromSingle($typeName, ['id' => '999']);
                                })
                                ->update(function (ModelInterface $owner, ModelInterface $modelToUpdate, array $saveFields) use ($r) {
                                    $this->testWatcher->info('update');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $modelToUpdate->apiResourcesGetId(),
                                        $modelToUpdate->apiResourcesGetType(),
                                        $r->getRelation()->getName()
                                    ]);

                                    $this->testWatcher->saveFields($saveFields);

                                    return $modelToUpdate;
                                })
                                ->delete(function (ModelInterface $owner, ModelInterface $modelToDelete) use ($r) {
                                    $this->testWatcher->info('delete');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $modelToDelete->apiResourcesGetId(),
                                        $modelToDelete->apiResourcesGetType(),
                                        $r->getRelation()->getName()
                                    ]);
                                })

                                // never called
                                ->addBeforeOwner(function (string $typeName, array $saveFields) use ($r) {
                                    $this->testWatcher->info('add_before_owner');
                                    return Model::fromSingle($typeName, ['id' => '999']);
                                });
                        });
                    });
            }
        );

        $this->request($api, data: ['other' => $data], params: ['id' => '111333']);

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedInfo2, $this->testWatcher->info2);
        $this->assertEquals($expectedSaveFields, $this->testWatcher->saveFields);
    }

    public function saveToOwnerUpdateDataProvider()
    {
        // $existingData, $data, $expectedInfo, $expectedInfo2, $expectedSaveFields
        return [
            'new_null' => [
                [],
                null,
                ['get', 'save_to_owner'],
                [
                    ['111333', 'TYPE', 'other'],
                    [null, null, 'other']
                ],
                []
            ],

            'new_empty_data' => [
                [],
                [],
                ['get', 'add', 'save_to_owner'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', 'TYPE', 'other'],
                    ['999', 'TYPE', 'other']
                ],
                [[]]
            ],

            'new_unknown_field' => [
                [],
                ['a' => 'b'],
                ['get', 'add', 'save_to_owner'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', 'TYPE', 'other'],
                    ['999', 'TYPE', 'other']
                ],
                [[]]
            ],

            'new_valid_field_no_id' => [
                [],
                ['name' => 'name1'],
                ['get', 'add', 'save_to_owner'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', 'TYPE', 'other'],
                    ['999', 'TYPE', 'other']
                ],
                [['name' => 'name1']]
            ],

            'new_valid_field_with_id' => [
                [],
                ['id' => '4', 'name' => 'name1'],
                ['get', 'add', 'save_to_owner'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', 'TYPE', 'other'],
                    ['999', 'TYPE', 'other']
                ],
                [['name' => 'name1']]
            ],

            'existing_null' => [
                ['id' => '10'],
                null,
                ['get', 'delete', 'save_to_owner'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                    [null, null, 'other']
                ],
                []
            ],

            'existing_empty_data' => [
                ['id' => '10'],
                [],
                ['get', 'update', 'save_to_owner'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                    ['10', 'TYPE', 'other']
                ],
                [[]]
            ],

            'existing_unknown_field' => [
                ['id' => '10'],
                ['a' => 'b'],
                ['get', 'update', 'save_to_owner'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                    ['10', 'TYPE', 'other']
                ],
                [[]]
            ],

            'existing_valid_field_no_id' => [
                ['id' => '10'],
                ['name' => 'name1'],
                ['get', 'update', 'save_to_owner'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                    ['10', 'TYPE', 'other']
                ],
                [['name' => 'name1']]
            ],

            'existing_valid_field_with_id' => [
                ['id' => '10'],
                ['id' => '4', 'name' => 'name1'],
                ['get', 'update', 'save_to_owner'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                    ['10', 'TYPE', 'other']
                ],
                [['name' => 'name1']]
            ],

            'existing_valid_field_same_id' => [
                ['id' => '4'],
                ['id' => '4', 'name' => 'name1'],
                ['get', 'update', 'save_to_owner'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '4', 'TYPE', 'other'],
                    ['4', 'TYPE', 'other']
                ],
                [['name' => 'name1']]
            ]
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
            $this->expectExceptionMessage('Get callback of resolver for relation other must return a ModelInterface object or null.');
        }

        $api = $this->createApiWithType(
            function (FieldBag $fields) use ($return) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) use ($return) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) use ($return) {
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
                    });
            }
        );

        $this->request($api, data: ['other' => []], params: ['id' => '111333']);

        $this->assertTrue(true);
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
     * @dataProvider addBeforeOwnerDoesNotReturnModelDataProvider
     */
    public function test_add_before_owner_does_not_return_model_or_null($return)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('AddBeforeOwner callback of resolver for relation other must return a ModelInterface object.');

        $api = $this->createApiWithType(
            function (FieldBag $fields) use ($return) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) use ($return) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) use ($return) {
                            $r
                                ->saveRelatedToOwner(fn () => [])
                                ->get(fn () => null)
                                ->addBeforeOwner(function () use ($return) {
                                    if ($return !== 'NOTHING') {
                                        return $return;
                                    }
                                })
                                ->add(fn () => null)
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            }
        );

        $this->request($api, data: ['other' => []]);

        $this->assertTrue(true);
    }

    public function addBeforeOwnerDoesNotReturnModelDataProvider()
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
     * @dataProvider addDoesNotReturnModelDataProvider
     */
    public function test_add_does_not_return_model_or_null($updateOwner, $return)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Add callback of resolver for relation other must return a ModelInterface object.');

        $api = $this->createApiWithType(
            function (FieldBag $fields) use ($return) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) use ($return) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) use ($return) {
                            $r
                                ->get(fn () => null)
                                ->add(function () use ($return) {
                                    if ($return !== 'NOTHING') {
                                        return $return;
                                    }
                                })
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            }
        );

        $params = $updateOwner ? ['id' => '123'] : [];
        $this->request($api, data: ['other' => []], params: $params);

        $this->assertTrue(true);
    }

    public function addDoesNotReturnModelDataProvider()
    {
        return [
            'create_null' => [false, null],
            'create_array' => [false, []],
            'create_string' => [false, 'string'],
            'create_object' => [false, new stdClass()],
            'create_nothing' => [false, 'NOTHING'],
            'update_null' => [true, null],
            'update_array' => [true, []],
            'update_string' => [true, 'string'],
            'update_object' => [true, new stdClass()],
            'update_nothing' => [true, 'NOTHING']
        ];
    }

    /**
     * @dataProvider addRecursiveDataProvider
     */
    public function test_add_recursive($update)
    {
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(fn () => null)
                                ->add(function (ModelInterface $owner, string $typeName, array $saveFields) use ($r) {
                                    $this->testWatcher->info('add_' . $saveFields['name']);

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);

                                    $this->testWatcher->saveFields($saveFields);

                                    return Model::fromSingle('TYPE', ['id' => 'id_' . $saveFields['name']]);
                                })
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            }
        );

        $data = [
            'name' => 'parent',
            'other' => [
                'name' => 'child1',
                'other' => [
                    'name' => 'child2',
                    'other' => [
                        'name' => 'child3',
                    ]
                ]
            ]
        ];

        $expectedInfo = ['add_child1', 'add_child2', 'add_child3'];

        $expectedInfo2 = [
            [$update ? 'parent' : '111333', 'TYPE', 'TYPE', 'other'],
            ['id_child1', 'TYPE', 'TYPE', 'other'],
            ['id_child2', 'TYPE', 'TYPE', 'other']
        ];

        $expectedSaveFields = [
            ['name' => 'child1'],
            ['name' => 'child2'],
            ['name' => 'child3']
        ];

        $params = $update ? ['id' => 'parent'] : [];
        $this->request($api, data: $data, params: $params);

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedInfo2, $this->testWatcher->info2);
        $this->assertEquals($expectedSaveFields, $this->testWatcher->saveFields);
    }

    public function addRecursiveDataProvider()
    {
        return [
            'update_owner' => [true],
            'add_owner' => [false],
        ];
    }

    public function test_update_recursive()
    {
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(function (ModelInterface $owner) {
                                    $childId = match ($owner->apiResourcesGetId()) {
                                        'parent' => 'id_child1',
                                        'id_child1' => 'id_child2',
                                        'id_child2' => 'id_child3'
                                    };
                                    return Model::fromSingle('TYPE', ['id' => $childId]);
                                })
                                ->add(fn () => null)
                                ->update(function (ModelInterface $owner, ModelInterface $modelToUpdate, array $saveFields) use ($r) {
                                    $this->testWatcher->info('update_' . $saveFields['name']);

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $modelToUpdate->apiResourcesGetId(),
                                        $modelToUpdate->apiResourcesGetType(),
                                        $r->getRelation()->getName()
                                    ]);

                                    $this->testWatcher->saveFields($saveFields);
                                })
                                ->delete(fn () => null);
                        });
                    });
            }
        );

        $data = [
            'name' => 'parent',
            'other' => [
                'name' => 'child1',
                'other' => [
                    'name' => 'child2',
                    'other' => [
                        'name' => 'child3',
                    ]
                ]
            ]
        ];

        $expectedInfo = ['update_child1', 'update_child2', 'update_child3'];

        $expectedInfo2 = [
            ['parent', 'TYPE', 'id_child1', 'TYPE', 'other'],
            ['id_child1', 'TYPE', 'id_child2', 'TYPE', 'other'],
            ['id_child2', 'TYPE', 'id_child3', 'TYPE', 'other']
        ];

        $expectedSaveFields = [
            ['name' => 'child1'],
            ['name' => 'child2'],
            ['name' => 'child3']
        ];

        $this->request($api, data: $data, params: ['id' => 'parent']);

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedInfo2, $this->testWatcher->info2);
        $this->assertEquals($expectedSaveFields, $this->testWatcher->saveFields);
    }

    public function test_add_before_owner_recursive()
    {
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->saveRelatedToOwner(fn (string $id, string $type) => [
                                    'related_id' => $id,
                                    'related_type' => $type
                                ])

                                ->addBeforeOwner(function (string $typeName, array $saveFields) use ($r) {
                                    $this->testWatcher->info('add_before_owner_' . $saveFields['name']);

                                    $this->testWatcher->info2([
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);

                                    $this->testWatcher->saveFields($saveFields);

                                    return Model::fromSingle('TYPE', ['id' => 'id_' . $saveFields['name']]);
                                })

                                ->get(fn () => null)
                                ->add(fn () => null)
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            }
        );

        $data = [
            'name' => 'parent',
            'other' => [
                'name' => 'child1',
                'other' => [
                    'name' => 'child2',
                    'other' => [
                        'name' => 'child3',
                    ]
                ]
            ]
        ];

        $expectedInfo = ['add_before_owner_child3', 'add_before_owner_child2', 'add_before_owner_child1'];

        $expectedInfo2 = [
            ['TYPE', 'other'],
            ['TYPE', 'other'],
            ['TYPE', 'other']
        ];

        $expectedSaveFields = [
            ['name' => 'child3'],
            ['name' => 'child2', 'related_id' => 'id_child3', 'related_type' => 'TYPE'],
            ['name' => 'child1', 'related_id' => 'id_child2', 'related_type' => 'TYPE']
        ];

        $this->request($api, data: $data);

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedInfo2, $this->testWatcher->info2);
        $this->assertEquals($expectedSaveFields, $this->testWatcher->saveFields);
    }

    public function test_add_before_owner_recursive_update_owner()
    {
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->saveRelatedToOwner(fn (string $id, string $type) => [
                                    'related_id' => $id,
                                    'related_type' => $type
                                ])

                                ->addBeforeOwner(function (string $typeName, array $saveFields) use ($r) {
                                    $this->testWatcher->info('add_before_owner_' . $saveFields['name']);

                                    $this->testWatcher->info2([
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);

                                    $this->testWatcher->saveFields($saveFields);

                                    return Model::fromSingle('TYPE', ['id' => 'id_' . $saveFields['name']]);
                                })

                                ->add(function (ModelInterface $owner, string $typeName, array $saveFields) use ($r) {
                                    $this->testWatcher->info('add_' . $saveFields['name']);

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);

                                    $this->testWatcher->saveFields($saveFields);

                                    return Model::fromSingle('TYPE', ['id' => 'id_' . $saveFields['name']]);
                                })

                                ->get(fn () => null)
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            }
        );

        $data = [
            'name' => 'parent',
            'other' => [
                'name' => 'child1',
                'other' => [
                    'name' => 'child2',
                    'other' => [
                        'name' => 'child3',
                    ]
                ]
            ]
        ];

        $expectedInfo = ['add_before_owner_child3', 'add_before_owner_child2', 'add_child1'];

        $expectedInfo2 = [
            ['TYPE', 'other'],
            ['TYPE', 'other'],
            ['parent', 'TYPE', 'TYPE', 'other']
        ];

        $expectedSaveFields = [
            ['name' => 'child3'],
            ['name' => 'child2', 'related_id' => 'id_child3', 'related_type' => 'TYPE'],
            ['name' => 'child1', 'related_id' => 'id_child2', 'related_type' => 'TYPE']
        ];

        $this->request($api, data: $data, params: ['id' => 'parent']);

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedInfo2, $this->testWatcher->info2);
        $this->assertEquals($expectedSaveFields, $this->testWatcher->saveFields);
    }
}
