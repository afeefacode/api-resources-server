<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\MutationRelationLinkOneResolver;
use Afeefa\ApiResources\Test\MutationRelationTest;

use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Type\Type;
use stdClass;

class MutationRelationLinkOneResolverTest extends MutationRelationTest
{
    /**
     * @dataProvider missingCallbacksDataProvider
     */
    public function test_missing_callbacks($missingCallback)
    {
        $this->expectException(MissingCallbackException::class);
        $n = $missingCallback === 'unlink' ? 'n' : '';
        $this->expectExceptionMessage("Resolver for relation other needs to implement a{$n} {$missingCallback}() method.");

        $api = $this->createApiWithType(
            function (FieldBag $fields) use ($missingCallback) {
                $fields
                    ->relation('other', Type::link(T('TYPE')), function (Relation $relation) use ($missingCallback) {
                        $relation->resolveSave(function (MutationRelationLinkOneResolver $r) use ($missingCallback) {
                            if ($missingCallback !== 'get') {
                                $r->get(fn () => null);
                            }
                            if ($missingCallback !== 'link') {
                                $r->link(fn () => null);
                            }
                            if ($missingCallback !== 'unlink') {
                                $r->unlink(fn () => null);
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
            ['link'],
            ['unlink']
        ];
    }

    public function test_with_all_callbacks()
    {
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->relation('other', Type::link(T('TYPE')), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationLinkOneResolver $r) {
                            $r
                                ->get(fn () => [])
                                ->link(fn () => null)
                                ->unlink(fn () => null);
                        });
                    });
            }
        );

        $this->request($api, data: ['other' => []]);

        $this->assertTrue(true);
    }

    public function test_save_to_owner_missing_callbacks()
    {
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->relation('other', Type::link(T('TYPE')), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationLinkOneResolver $r) {
                            $r->saveRelatedToOwner(fn () => []);
                        });
                    });
            }
        );

        $this->request($api, data: ['other' => []]);

        $this->assertTrue(true);
    }

    private $link_existingData = [];

    /**
     * @dataProvider updateOwnerDataProvider
     */
    public function test_update_owner($existingData, $data, $expectedInfo, $expectedInfo2)
    {
        $this->link_existingData = $existingData;

        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', Type::link(T('TYPE')), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationLinkOneResolver $r) {
                            $r
                                ->get(function (ModelInterface $owner) use ($r) {
                                    $this->testWatcher->info('get');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $r->getRelation()->getName()
                                    ]);

                                    if ($this->link_existingData) {
                                        return Model::fromSingle('TYPE', $this->link_existingData);
                                    }
                                    return null;
                                })
                                ->link(function (ModelInterface $owner, ?string $id, string $typeName) use ($r) {
                                    $this->testWatcher->info('link');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $id,
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);
                                })
                                ->unlink(function (ModelInterface $owner, ModelInterface $modelToUnlink) use ($r) {
                                    $this->testWatcher->info('unlink');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $modelToUnlink->apiResourcesGetId(),
                                        $modelToUnlink->apiResourcesGetType(),
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
    }

    public function updateOwnerDataProvider()
    {
        // $existingData, $data, $expectedInfo, $expectedInfo2
        return [
            'new_null' => [
                [],
                null,
                ['get'],
                [['111333', 'TYPE', 'other']]
            ],

            'new_empty_data' => [
                [],
                [],
                ['get'],
                [['111333', 'TYPE', 'other']]
            ],

            'new_unknown_field' => [
                [],
                ['a' => 'b'],
                ['get'],
                [['111333', 'TYPE', 'other']]
            ],

            'new_valid_field_no_id' => [
                [],
                ['name' => 'name1'],
                ['get'],
                [['111333', 'TYPE', 'other']]
            ],

            'new_valid_field_with_id' => [
                [],
                ['id' => '4', 'name' => 'name1'],
                ['get', 'link'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '4', 'TYPE', 'other']
                ]
            ],

            'existing_null' => [
                ['id' => '10'],
                null,
                ['get', 'unlink'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other']
                ]
            ],

            'existing_empty_data' => [
                ['id' => '10'],
                [],
                ['get', 'unlink'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                ]
            ],

            'existing_unknown_field' => [
                ['id' => '10'],
                ['a' => 'b'],
                ['get', 'unlink'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                ]
            ],

            'existing_valid_field_no_id' => [
                ['id' => '10'],
                ['name' => 'name1'],
                ['get', 'unlink'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                ]
            ],

            'existing_valid_field_with_id' => [
                ['id' => '10'],
                ['id' => '4', 'name' => 'name1'],
                ['get', 'unlink', 'link'],
                [
                    ['111333', 'TYPE', 'other'],
                    ['111333', 'TYPE', '10', 'TYPE', 'other'],
                    ['111333', 'TYPE', '4', 'TYPE', 'other']
                ]
            ],

            'existing_valid_field_same_id' => [
                ['id' => '4'],
                ['id' => '4', 'name' => 'name1'],
                ['get'],
                [['111333', 'TYPE', 'other']]
            ]
        ];
    }

    /**
     * @dataProvider createOwnerDataProvider
     */
    public function test_create_owner($data, $expectedInfo, $expectedInfo2)
    {
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', Type::link(T('TYPE')), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationLinkOneResolver $r) {
                            $r
                                ->get(function () {
                                    $this->testWatcher->info('get'); // never called
                                    return null;
                                })
                                ->link(function (ModelInterface $owner, ?string $id, string $typeName) use ($r) {
                                    $this->testWatcher->info('link');

                                    $this->testWatcher->info2([
                                        $owner->apiResourcesGetId(),
                                        $owner->apiResourcesGetType(),
                                        $id,
                                        $typeName,
                                        $r->getRelation()->getName()
                                    ]);
                                })
                                ->unlink(function () {
                                    $this->testWatcher->info('unlink');
                                });
                        });
                    });
            }
        );

        $this->request($api, data: ['other' => $data]);

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedInfo2, $this->testWatcher->info2);
    }

    public function createOwnerDataProvider()
    {
        // $data, $expectedInfo, $expectedInfo2
        return [
            'new_null' => [
                null,
                [],
                []
            ],

            'new_empty_data' => [
                [],
                [],
                []
            ],

            'new_unknown_field' => [
                ['a' => 'b'],
                [],
                []
            ],

            'new_valid_field_no_id' => [
                ['name' => 'name1'],
                [],
                []
            ],

            'new_valid_field_with_id' => [
                ['id' => '4', 'name' => 'name1'],
                ['link'],
                [['111333', 'TYPE', '4', 'TYPE', 'other']]
            ]
        ];
    }

    /**
     * @dataProvider saveToOwnerDataProvider
     */
    public function test_save_to_owner($data, $expectedInfo, $expectedInfo2)
    {
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->relation('other', Type::link(T('TYPE')), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationLinkOneResolver $r) {
                            $r->saveRelatedToOwner(function (?string $id, ?string $typeName) use ($r) {
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
                            });
                        });
                    });
            }
        );

        $this->request($api, data: ['other' => $data]);

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedInfo2, $this->testWatcher->info2);
    }

    public function saveToOwnerDataProvider()
    {
        // $data, $expectedInfo, $expectedInfo2
        return [
            'null' => [
                null,
                ['save_to_owner'],
                [[null, null, 'other']]
            ],

            'empty_data' => [
                null,
                ['save_to_owner'],
                [[null, null, 'other']]
            ],

            'unknown_field' => [
                ['a' => 'b'],
                ['save_to_owner'],
                [[null, null, 'other']]
            ],

            'no_id' => [
                ['name' => 'name1'],
                ['save_to_owner'],
                [[null, null, 'other']]
            ],

            'id' => [
                ['id' => '4'],
                ['save_to_owner'],
                [['4', 'TYPE', 'other']]
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
                        $relation->resolveSave(function (MutationRelationLinkOneResolver $r) use ($return) {
                            $r
                                ->get(function () use ($return) {
                                    if ($return !== 'NOTHING') {
                                        return $return;
                                    }
                                })
                                ->link(fn () => null)
                                ->unlink(fn () => null);
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
}
