<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\MutationActionSimpleResolver;
use Afeefa\ApiResources\Resolver\MutationRelationHasOneResolver;
use Afeefa\ApiResources\Test\MutationTest;
use function Afeefa\ApiResources\Test\T;
use Closure;
use stdClass;

class MutationActionSimpleResolverTest extends MutationTest
{
    public function test_missing_save_callback()
    {
        $this->expectException(MissingCallbackException::class);
        $this->expectExceptionMessage('Resolver for action ACT on resource RES needs to implement a save() method.');

        $api = $this->createApiWithMutation(
            fn () => T('TYPE'),
            function (Action $action) {
                $action->resolve(function (MutationActionSimpleResolver $r) {
                });
            }
        );

        $this->request($api, data: ['other' => []]);
    }

    public function test_with_save_callback()
    {
        $api = $this->createApiWithMutation(
            fn () => T('TYPE'),
            function (Action $action) {
                $action->resolve(function (MutationActionSimpleResolver $r) {
                    $r->save(fn () => Model::fromSingle('TYPE', []));
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
        $api = $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields->attribute('name', StringAttribute::class);
            },
            fn () => T('TYPE'),
            function (Action $action) {
                $action
                    ->resolve(function (MutationActionSimpleResolver $r) {
                        $r
                            ->save(function (array $saveFields) {
                                $this->testWatcher->info('save');
                                $this->testWatcher->saveFields($saveFields);
                                return Model::fromSingle('TYPE', []);
                            });
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
                ['id' => '123', 'name' => 'hase', 'nix' => 'ignored'],
                ['save'],
                [['name' => 'hase']]
            ],

            'without_id' => [
                false,
                ['name' => 'hase', 'nix' => 'ignored'],
                ['save'],
                [['name' => 'hase']]
            ]
        ];
    }

    /**
     * @dataProvider saveFieldsDataProvider
     */
    public function test_save_fields($fields, $expectedFields)
    {
        $api = $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->attribute('title', StringAttribute::class);
            },
            fn () => T('TYPE'),
            function (Action $action) {
                $action
                    ->resolve(function (MutationActionSimpleResolver $r) {
                        $r
                            ->save(function (array $saveFields) {
                                $this->testWatcher->saveFields($saveFields);
                                return Model::fromSingle('TYPE', []);
                            });
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

            'with_id' => [
                ['id' => '123', 'title' => 'title1'],
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
     * @dataProvider saveIgnoresRelationsDataProvider
     */
    public function test_save_ignores_relations($fields, $expectedFields, $expectedOrder)
    {
        $api = $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields
                    ->attribute('name', StringAttribute::class)
                    ->attribute('title', StringAttribute::class)
                    ->relation('relation', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(function () {
                                    $this->testWatcher->info('relation_get');
                                })
                                ->add(function () {
                                    $this->testWatcher->info('relation_add');
                                })
                                ->update(function () {
                                    $this->testWatcher->info('relation_update');
                                })
                                ->delete(function () {
                                    $this->testWatcher->info('relation_delete');
                                });
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) {
                $action
                    ->resolve(function (MutationActionSimpleResolver $r) {
                        $r
                            ->save(function (array $saveFields) {
                                $this->testWatcher->info('owner');
                                $this->testWatcher->saveFields($saveFields);
                                return Model::fromSingle('TYPE', ['id' => '3']);
                            });
                    });
            }
        );

        $this->request(
            $api,
            data: $fields
        );

        $this->assertEquals($expectedFields, $this->testWatcher->saveFields);

        $this->assertEquals($expectedOrder, $this->testWatcher->info);
    }

    public function saveIgnoresRelationsDataProvider()
    {
        // [requested fields, get relation, expected save fields, resolve order]
        $data = [
            'relation' => [
                ['name' => 'name1', 'relation' => ['name' => 'name2']],
                [['name' => 'name1']],
                ['owner']
            ],

            'relation_null' => [
                ['name' => 'name1', 'relation' => null],
                [['name' => 'name1']],
                ['owner']
            ],
        ];

        return $data;
    }

    /**
     * @dataProvider saveDoesNotReturnModelDataProvider
     */
    public function test_save_does_not_return_model($return)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Save callback of mutation resolver for action ACT on resource RES must return a ModelInterface object or null.');

        $api = $this->createApiWithMutation(
            fn () => T('TYPE'),
            function (Action $action) use ($return) {
                $action
                    ->resolve(function (MutationActionSimpleResolver $r) use ($return) {
                        $r
                            ->save(function () use ($return) {
                                if ($return !== 'NOTHING') {
                                    return $return;
                                }
                            });
                    });
            }
        );

        $this->request($api);
    }

    public function saveDoesNotReturnModelDataProvider()
    {
        return [
            'array' => [[]],
            'string' => ['string'],
            'object' => [new stdClass()]
        ];
    }

    /**
     * @dataProvider saveReturnsNullDataProvider
     */
    public function test_save_returns_null($return)
    {
        $api = $this->createApiWithMutation(
            fn () => T('TYPE'),
            function (Action $action) use ($return) {
                $action
                    ->resolve(function (MutationActionSimpleResolver $r) use ($return) {
                        $r
                            ->save(function () use ($return) {
                                if ($return !== 'NOTHING') {
                                    return $return;
                                }
                            });
                    });
            }
        );

        $result = $this->request($api);

        $this->assertNull($result['data']);
    }

    public function saveReturnsNullDataProvider()
    {
        return [
            'array' => [null],
            'array' => ['NOTHING']
        ];
    }

    public function test_transaction()
    {
        $api = $this->createApiWithMutation(
            fn () => T('TYPE'),
            function (Action $action) {
                $action
                    ->resolve(function (MutationActionSimpleResolver $r) {
                        $r
                            ->transaction(function (Closure $execute) {
                                $this->testWatcher->info('startTransaction');
                                $execute();
                                $this->testWatcher->info('endTransaction');
                                return ['data' => 'null'];
                            })
                            ->save(function () {
                                $this->testWatcher->info('save');
                                return Model::fromSingle('TYPE', []);
                            });
                    });
            }
        );

        $this->request($api);

        $this->assertEquals(['startTransaction', 'save', 'endTransaction'], $this->testWatcher->info);
    }

    public function test_transaction_does_not_return_result_array()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Transaction callback of mutation resolver for action ACT on resource RES must return a result array with at least a data field.');

        $api = $this->createApiWithMutation(
            fn () => T('TYPE'),
            function (Action $action) {
                $action
                    ->resolve(function (MutationActionSimpleResolver $r) {
                        $r->transaction(fn () => null);
                    });
            }
        );

        $this->request($api);
    }
}
