<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\MutationRelationHasOneResolver;
use Afeefa\ApiResources\Test\MutationRelationTest;

use function Afeefa\ApiResources\Test\T;

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
}
