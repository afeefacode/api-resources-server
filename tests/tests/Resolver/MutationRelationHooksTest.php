<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\MutationActionModelResolver;
use Afeefa\ApiResources\Resolver\MutationRelationHasManyResolver;
use Afeefa\ApiResources\Resolver\MutationRelationHasOneResolver;
use Afeefa\ApiResources\Test\MutationTest;

use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Type\Type;

class MutationRelationHooksTest extends MutationTest
{
    // HasOne: beforeAddRelation hook

    public function test_has_one_before_add_relation_on_create_owner()
    {
        $api = $this->createApiWithUpdateType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(fn () => null)
                                ->add(function (ModelInterface $owner, string $typeName, array $saveFields) {
                                    $this->testWatcher->info('add');
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle('TYPE');
                                })
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            }
        );

        $this->request($api, data: [
            'name' => 'owner1',
            'other' => ['name' => 'child1']
        ]);

        $this->assertEquals(['add'], $this->testWatcher->info);
        $this->assertEquals([['name' => 'child1']], $this->testWatcher->saveFields);
    }

    public function test_has_one_before_add_relation_modifies_save_fields()
    {
        $hookCalled = false;
        $hookData = null;
        $hookRelationName = null;
        $hookTypeName = null;

        $api = $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(fn () => null)
                                ->add(function (ModelInterface $owner, string $typeName, array $saveFields) {
                                    $this->testWatcher->info('add');
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle('TYPE');
                                })
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) use (&$hookCalled, &$hookData, &$hookRelationName, &$hookTypeName) {
                $action
                    ->resolve(function (MutationActionModelResolver $r) use (&$hookCalled, &$hookData, &$hookRelationName, &$hookTypeName) {
                        $r
                            ->get(fn ($id) => Model::fromSingle('TYPE', ['id' => $id]))
                            ->update(fn ($model) => $model)
                            ->add(fn () => Model::fromSingle('TYPE', ['id' => '111333']))
                            ->delete(fn () => null)
                            ->beforeAddRelation(function ($data, $relationName, $typeName, $saveFields) use (&$hookCalled, &$hookData, &$hookRelationName, &$hookTypeName) {
                                $hookCalled = true;
                                $hookData = $data;
                                $hookRelationName = $relationName;
                                $hookTypeName = $typeName;
                                $saveFields['injected'] = 'value';
                                return $saveFields;
                            });
                    });
            }
        );

        $this->request($api, data: [
            'name' => 'owner1',
            'other' => ['name' => 'child1']
        ]);

        $this->assertTrue($hookCalled);
        $this->assertEquals('other', $hookRelationName);
        $this->assertEquals('TYPE', $hookTypeName);
        $this->assertArrayHasKey('name', $hookData);
        $this->assertEquals('owner1', $hookData['name']);
        $this->assertEquals([['name' => 'child1', 'injected' => 'value']], $this->testWatcher->saveFields);
    }

    // HasOne: beforeUpdateRelation hook

    public function test_has_one_before_update_relation_modifies_save_fields()
    {
        $hookCalled = false;
        $hookRelationName = null;
        $hookExistingModel = null;

        $api = $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(fn () => Model::fromSingle('TYPE', ['id' => '10']))
                                ->add(fn () => Model::fromSingle('TYPE'))
                                ->update(function (ModelInterface $owner, ModelInterface $modelToUpdate, array $saveFields) {
                                    $this->testWatcher->info('update');
                                    $this->testWatcher->saveFields($saveFields);
                                })
                                ->delete(fn () => null);
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) use (&$hookCalled, &$hookRelationName, &$hookExistingModel) {
                $action
                    ->resolve(function (MutationActionModelResolver $r) use (&$hookCalled, &$hookRelationName, &$hookExistingModel) {
                        $r
                            ->get(fn ($id) => Model::fromSingle('TYPE', ['id' => $id]))
                            ->update(fn ($model) => $model)
                            ->add(fn () => Model::fromSingle('TYPE', ['id' => '111333']))
                            ->delete(fn () => null)
                            ->beforeUpdateRelation(function ($data, $relationName, $existingModel, $saveFields) use (&$hookCalled, &$hookRelationName, &$hookExistingModel) {
                                $hookCalled = true;
                                $hookRelationName = $relationName;
                                $hookExistingModel = $existingModel;
                                $saveFields['modified'] = true;
                                return $saveFields;
                            });
                    });
            }
        );

        $this->request($api, data: [
            'name' => 'owner1',
            'other' => ['name' => 'updated_child']
        ], params: ['id' => '111333']);

        $this->assertTrue($hookCalled);
        $this->assertEquals('other', $hookRelationName);
        $this->assertInstanceOf(ModelInterface::class, $hookExistingModel);
        $this->assertEquals('10', $hookExistingModel->apiResourcesGetId());
        $this->assertEquals([['name' => 'updated_child', 'modified' => true]], $this->testWatcher->saveFields);
    }

    // HasOne: beforeDeleteRelation hook

    public function test_has_one_before_delete_relation_is_called()
    {
        $hookCalled = false;
        $hookRelationName = null;
        $hookExistingModel = null;
        $hookData = null;

        $api = $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(fn () => Model::fromSingle('TYPE', ['id' => '10']))
                                ->add(fn () => Model::fromSingle('TYPE'))
                                ->update(fn () => null)
                                ->delete(function (ModelInterface $owner, ModelInterface $modelToDelete) {
                                    $this->testWatcher->info('delete');
                                });
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) use (&$hookCalled, &$hookRelationName, &$hookExistingModel, &$hookData) {
                $action
                    ->resolve(function (MutationActionModelResolver $r) use (&$hookCalled, &$hookRelationName, &$hookExistingModel, &$hookData) {
                        $r
                            ->get(fn ($id) => Model::fromSingle('TYPE', ['id' => $id]))
                            ->update(fn ($model) => $model)
                            ->add(fn () => Model::fromSingle('TYPE', ['id' => '111333']))
                            ->delete(fn () => null)
                            ->beforeDeleteRelation(function ($data, $relationName, $existingModel) use (&$hookCalled, &$hookRelationName, &$hookExistingModel, &$hookData) {
                                $hookCalled = true;
                                $hookData = $data;
                                $hookRelationName = $relationName;
                                $hookExistingModel = $existingModel;
                            });
                    });
            }
        );

        $this->request($api, data: [
            'name' => 'owner1',
            'other' => null
        ], params: ['id' => '111333']);

        $this->assertTrue($hookCalled);
        $this->assertEquals('other', $hookRelationName);
        $this->assertInstanceOf(ModelInterface::class, $hookExistingModel);
        $this->assertEquals('10', $hookExistingModel->apiResourcesGetId());
        $this->assertArrayHasKey('name', $hookData);
        $this->assertEquals(['delete'], $this->testWatcher->info);
    }

    // HasMany: beforeAddRelation hook

    public function test_has_many_before_add_relation_modifies_save_fields()
    {
        $hookCalls = [];

        $api = $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasManyResolver $r) {
                            $r
                                ->get(fn () => [])
                                ->add(function (ModelInterface $owner, string $typeName, array $saveFields) {
                                    $this->testWatcher->info('add');
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle('TYPE');
                                })
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) use (&$hookCalls) {
                $action
                    ->resolve(function (MutationActionModelResolver $r) use (&$hookCalls) {
                        $r
                            ->get(fn ($id) => Model::fromSingle('TYPE', ['id' => $id]))
                            ->update(fn ($model) => $model)
                            ->add(fn () => Model::fromSingle('TYPE', ['id' => '111333']))
                            ->delete(fn () => null)
                            ->beforeAddRelation(function ($data, $relationName, $typeName, $saveFields) use (&$hookCalls) {
                                $hookCalls[] = [
                                    'relationName' => $relationName,
                                    'typeName' => $typeName
                                ];
                                $saveFields['injected'] = 'value';
                                return $saveFields;
                            });
                    });
            }
        );

        $this->request($api, data: [
            'name' => 'owner1',
            'others' => [
                ['name' => 'child1'],
                ['name' => 'child2']
            ]
        ]);

        $this->assertCount(2, $hookCalls);
        $this->assertEquals('others', $hookCalls[0]['relationName']);
        $this->assertEquals('TYPE', $hookCalls[0]['typeName']);
        $this->assertEquals([
            ['name' => 'child1', 'injected' => 'value'],
            ['name' => 'child2', 'injected' => 'value']
        ], $this->testWatcher->saveFields);
    }

    // HasMany: beforeUpdateRelation hook

    public function test_has_many_before_update_relation_modifies_save_fields()
    {
        $hookCalls = [];

        $api = $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasManyResolver $r) {
                            $r
                                ->get(fn () => Model::fromList('TYPE', [
                                    ['id' => '1'],
                                    ['id' => '2']
                                ]))
                                ->add(fn () => Model::fromSingle('TYPE'))
                                ->update(function (ModelInterface $owner, ModelInterface $modelToUpdate, array $saveFields) {
                                    $this->testWatcher->info('update');
                                    $this->testWatcher->saveFields($saveFields);
                                })
                                ->delete(fn () => null);
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) use (&$hookCalls) {
                $action
                    ->resolve(function (MutationActionModelResolver $r) use (&$hookCalls) {
                        $r
                            ->get(fn ($id) => Model::fromSingle('TYPE', ['id' => $id]))
                            ->update(fn ($model) => $model)
                            ->add(fn () => Model::fromSingle('TYPE', ['id' => '111333']))
                            ->delete(fn () => null)
                            ->beforeUpdateRelation(function ($data, $relationName, $existingModel, $saveFields) use (&$hookCalls) {
                                $hookCalls[] = [
                                    'relationName' => $relationName,
                                    'existingId' => $existingModel->apiResourcesGetId()
                                ];
                                $saveFields['modified'] = true;
                                return $saveFields;
                            });
                    });
            }
        );

        $this->request($api, data: [
            'name' => 'owner1',
            'others' => [
                ['id' => '1', 'name' => 'updated1'],
                ['id' => '2', 'name' => 'updated2']
            ]
        ], params: ['id' => '111333']);

        $this->assertCount(2, $hookCalls);
        $this->assertEquals('others', $hookCalls[0]['relationName']);
        $this->assertEquals('1', $hookCalls[0]['existingId']);
        $this->assertEquals('2', $hookCalls[1]['existingId']);
        $this->assertEquals([
            ['name' => 'updated1', 'modified' => true],
            ['name' => 'updated2', 'modified' => true]
        ], $this->testWatcher->saveFields);
    }

    // HasMany: beforeDeleteRelation hook

    public function test_has_many_before_delete_relation_is_called()
    {
        $hookCalls = [];

        $api = $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->relation('others', Type::list(T('TYPE')), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasManyResolver $r) {
                            $r
                                ->get(fn () => Model::fromList('TYPE', [
                                    ['id' => '1'],
                                    ['id' => '2'],
                                    ['id' => '3']
                                ]))
                                ->add(fn () => Model::fromSingle('TYPE'))
                                ->update(fn () => null)
                                ->delete(function (ModelInterface $owner, ModelInterface $modelToDelete) {
                                    $this->testWatcher->info('delete_' . $modelToDelete->apiResourcesGetId());
                                });
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) use (&$hookCalls) {
                $action
                    ->resolve(function (MutationActionModelResolver $r) use (&$hookCalls) {
                        $r
                            ->get(fn ($id) => Model::fromSingle('TYPE', ['id' => $id]))
                            ->update(fn ($model) => $model)
                            ->add(fn () => Model::fromSingle('TYPE', ['id' => '111333']))
                            ->delete(fn () => null)
                            ->beforeDeleteRelation(function ($data, $relationName, $existingModel) use (&$hookCalls) {
                                $hookCalls[] = [
                                    'relationName' => $relationName,
                                    'deletedId' => $existingModel->apiResourcesGetId()
                                ];
                            });
                    });
            }
        );

        // Send only id '1', so '2' and '3' should be deleted
        $this->request($api, data: [
            'name' => 'owner1',
            'others' => [
                ['id' => '1', 'name' => 'keep']
            ]
        ], params: ['id' => '111333']);

        $this->assertCount(2, $hookCalls);
        $this->assertEquals('others', $hookCalls[0]['relationName']);
        $this->assertEquals('2', $hookCalls[0]['deletedId']);
        $this->assertEquals('3', $hookCalls[1]['deletedId']);
        $this->assertEquals(['delete_2', 'delete_3'], $this->testWatcher->info);
    }

    // Hooks not called when no callbacks set

    public function test_hooks_not_called_when_not_set()
    {
        $api = $this->createApiWithUpdateType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(fn () => null)
                                ->add(function (ModelInterface $owner, string $typeName, array $saveFields) {
                                    $this->testWatcher->info('add');
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle('TYPE');
                                })
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            }
        );

        // Default MutationTest resolver doesn't set beforeAddRelation etc.
        $this->request($api, data: [
            'name' => 'owner1',
            'other' => ['name' => 'child1']
        ]);

        $this->assertEquals(['add'], $this->testWatcher->info);
        // saveFields should be unmodified
        $this->assertEquals([['name' => 'child1']], $this->testWatcher->saveFields);
    }

    // Hook receives owner data ($data parameter)

    public function test_hook_receives_owner_data()
    {
        $receivedData = null;

        $api = $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(fn () => null)
                                ->add(function (ModelInterface $owner, string $typeName, array $saveFields) {
                                    $this->testWatcher->info('add');
                                    return Model::fromSingle('TYPE');
                                })
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) use (&$receivedData) {
                $action
                    ->resolve(function (MutationActionModelResolver $r) use (&$receivedData) {
                        $r
                            ->get(fn ($id) => Model::fromSingle('TYPE', ['id' => $id]))
                            ->update(fn ($model) => $model)
                            ->add(fn () => Model::fromSingle('TYPE', ['id' => '111333']))
                            ->delete(fn () => null)
                            ->beforeAddRelation(function ($data, $relationName, $typeName, $saveFields) use (&$receivedData) {
                                $receivedData = $data;
                                return $saveFields;
                            });
                    });
            }
        );

        $this->request($api, data: [
            'name' => 'test_owner',
            'other' => ['name' => 'child1']
        ]);

        $this->assertIsArray($receivedData);
        $this->assertArrayHasKey('name', $receivedData);
        $this->assertEquals('test_owner', $receivedData['name']);
        $this->assertArrayHasKey('other', $receivedData);
    }

    // HasOne: beforeAddRelation with saveRelatedToOwner (addBeforeOwner)

    public function test_has_one_before_add_relation_with_add_before_owner()
    {
        $hookCalled = false;

        $api = $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->relation('other', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->saveRelatedToOwner(fn (?string $id, ?string $typeName) => [
                                    'related_id' => $id,
                                    'related_type' => $typeName
                                ])
                                ->addBeforeOwner(function (string $typeName, array $saveFields) {
                                    $this->testWatcher->info('add_before_owner');
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle($typeName, ['id' => '101010']);
                                })
                                ->get(fn () => null)
                                ->add(fn () => null)
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) use (&$hookCalled) {
                $action
                    ->resolve(function (MutationActionModelResolver $r) use (&$hookCalled) {
                        $r
                            ->get(fn ($id) => Model::fromSingle('TYPE', ['id' => $id]))
                            ->update(fn ($model) => $model)
                            ->add(fn () => Model::fromSingle('TYPE', ['id' => '111333']))
                            ->delete(fn () => null)
                            ->beforeAddRelation(function ($data, $relationName, $typeName, $saveFields) use (&$hookCalled) {
                                $hookCalled = true;
                                $saveFields['from_hook'] = true;
                                return $saveFields;
                            });
                    });
            }
        );

        $this->request($api, data: [
            'name' => 'owner1',
            'other' => ['name' => 'child1']
        ]);

        $this->assertTrue($hookCalled);
        $this->assertEquals(['add_before_owner'], $this->testWatcher->info);
        $this->assertEquals([['name' => 'child1', 'from_hook' => true]], $this->testWatcher->saveFields);
    }
}
