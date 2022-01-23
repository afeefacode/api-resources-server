<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\MutationRelationLinkManyResolver;
use Afeefa\ApiResources\Test\MutationRelationTest;

use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Type\Type;
use stdClass;

class MutationRelationLinkManyResolverTest extends MutationRelationTest
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
                    ->relation('other', Type::list(Type::link(T('TYPE'))), function (Relation $relation) use ($missingCallback) {
                        $relation->resolveSave(function (MutationRelationLinkManyResolver $r) use ($missingCallback) {
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
                    ->relation('other', Type::list(Type::link(T('TYPE'))), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationLinkManyResolver $r) {
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

    /**
     * @dataProvider createOwnerDataProvider
     */
    public function test_create_owner($data, $expectedInfo, $expectedInfo2)
    {
        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', Type::list(Type::link(T('TYPE'))), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationLinkManyResolver $r) {
                            $r
                                ->get(function () {
                                    $this->testWatcher->info('get');
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
            'new_empty' => [
                [],
                [],
                []
            ],

            'new_unknown_field' => [
                [['a' => 'b']],
                [],
                []
            ],

            'new_valid_field_no_id' => [
                [['name' => 'name1']],
                [],
                []
            ],

            'new_valid_field_with_id' => [
                [['id' => '4', 'name' => 'name1']],
                ['link'],
                [['111333', 'TYPE', '4', 'TYPE', 'other']]
            ]
        ];
    }

    private $test_update_owner_existingData = [];

    /**
     * @dataProvider updateOwnerDataProvider
     */
    public function test_update_owner($existingData, $data, $expectedInfo, $expectedInfo2)
    {
        $this->test_update_owner_existingData = $existingData;

        $api = $this->createApiWithType(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('other', Type::list(Type::link(T('TYPE'))), function (Relation $relation) {
                        $relation->resolveSave(function (MutationRelationLinkManyResolver $r) {
                            $r
                                ->get(function () {
                                    $this->testWatcher->info('get');
                                    if ($this->test_update_owner_existingData) {
                                        return Model::fromList('TYPE', $this->test_update_owner_existingData);
                                    }
                                    return [];
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
            'new_empty' => [
                [],
                [],
                ['get'],
                []
            ],

            'new_unknown_field' => [
                [],
                [['a' => 'b']],
                ['get'],
                []
            ],

            'new_valid_field_no_id' => [
                [],
                [['name' => 'name1']],
                ['get'],
                []
            ],

            'new_valid_field_with_id' => [
                [],
                [['id' => '4', 'name' => 'name1']],
                ['get', 'link'],
                [['111333', 'TYPE', '4', 'TYPE', 'other']]
            ],

            'existing_empty' => [
                [['id' => '10'], ['id' => '11']],
                [],
                ['get', 'unlink', 'unlink'],
                [['111333', 'TYPE', '10', 'TYPE', 'other'], ['111333', 'TYPE', '11', 'TYPE', 'other']]
            ],

            'existing_unknown_field' => [
                [['id' => '10'], ['id' => '11']],
                [['a' => 'b'], ['id' => '11']],
                ['get', 'unlink'],
                [['111333', 'TYPE', '10', 'TYPE', 'other']]
            ],

            'unlink_not_present' => [
                [['id' => '10'], ['id' => '11']],
                [['id' => '11']],
                ['get', 'unlink'],
                [['111333', 'TYPE', '10', 'TYPE', 'other']]
            ],

            'unlink_link' => [
                [['id' => '10']],
                [['id' => '4']],
                ['get', 'unlink', 'link'],
                [['111333', 'TYPE', '10', 'TYPE', 'other'], ['111333', 'TYPE', '4', 'TYPE', 'other']]
            ],

            'keep' => [
                [['id' => '4'], ['id' => '5']],
                [['id' => '4'], ['id' => '5']],
                ['get'],
                []
            ],

            'keep_unlink_link' => [
                [['id' => '4'], ['id' => '5'], ['id' => '6'], ['id' => '7']],
                [['id' => '4'], ['id' => '5'], ['id' => '8'], ['id' => '9']],
                ['get', 'unlink', 'unlink', 'link', 'link'],
                [
                    ['111333', 'TYPE', '6', 'TYPE', 'other'], ['111333', 'TYPE', '7', 'TYPE', 'other'],
                    ['111333', 'TYPE', '8', 'TYPE', 'other'], ['111333', 'TYPE', '9', 'TYPE', 'other']
                ]
            ]
        ];
    }

    /**
     * @dataProvider getDoesNotReturnModelsDataProvider
     */
    public function test_get_does_not_return_array_of_models($return)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Get callback of resolver for relation other must return an array of ModelInterface objects.');

        $api = $this->createApiWithType(
            function (FieldBag $fields) use ($return) {
                $fields
                    ->relation('other', T('TYPE'), function (Relation $relation) use ($return) {
                        $relation->resolveSave(function (MutationRelationLinkManyResolver $r) use ($return) {
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

    public function getDoesNotReturnModelsDataProvider()
    {
        return [
            'null' => [null],
            'array_of_null' => [[null, null]],
            'string' => ['string'],
            'array_of_strings' => [['string', 'string']],
            'object' => [new stdClass()],
            'array_of_objects' => [[new stdClass(), new stdClass()]],
            'nothing' => ['NOTHING']
        ];
    }
}
