<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Field\Attribute;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\Model as BaseModel;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\MutationActionModelResolver;
use Afeefa\ApiResources\Resolver\MutationRelationHasManyResolver;
use Afeefa\ApiResources\Resolver\MutationRelationHasOneResolver;
use Afeefa\ApiResources\Resolver\MutationRelationLinkManyResolver;
use Afeefa\ApiResources\Resolver\MutationRelationLinkOneResolver;
use Afeefa\ApiResources\Test\MutationTest;
use function Afeefa\ApiResources\Test\T;
use Afeefa\ApiResources\Type\Type;

use Afeefa\ApiResources\Type\TypeClassMap;

class MutationActionModelResolverRelationsTest extends MutationTest
{
    protected Model $model;
    protected array $modelIdMap = [];

    protected function setUp(): void
    {
        parent::setup();

        $this->model = Model::fromSingle('TYPE');
        $this->modelIdMap = [];
    }

    public function test_create()
    {
        $this->save(
            data: [
                'title' => 'model1',
                'has_one' => [
                    'title' => 'hasOne1'
                ],
                'has_one_before' => [
                    'title' => 'hasOneBefore1'
                ],
                'has_many' => [
                    ['title' => 'hasMany1'],
                    ['title' => 'hasMany2']
                ],
                'link_one' => [
                    'id' => 'linkOne1'
                ],
                'link_one_before' => [
                    'id' => 'linkOneBefore1'
                ],
                'link_many' => [
                    ['id' => 'linkMany1'],
                    ['id' => 'linkMany2']
                ]
            ]
        );

        $expectedInfo = [
            'hasOneBeforeAddBeforeOwner_hasOneBefore1',
            'hasOneBeforeSaveRelatedToOwner_hasOneBefore1',
            'linkOneBeforeExists_linkOneBefore1',
            'linkOneBeforeSave_linkOneBefore1',
            'modelAdd_model1',
            'hasOneSaveOwnerToRelated_model1',
            'hasOneAdd_hasOne1',
            'hasManySaveOwnerToRelated_model1',
            'hasManyAdd_hasMany1',
            'hasManyAdd_hasMany2',
            'linkOneExists_linkOne1',
            'linkOneLink_linkOne1',
            'linkManyExists_linkMany1',
            'linkManyLink_linkMany1',
            'linkManyExists_linkMany2',
            'linkManyLink_linkMany2'
        ];

        $expectedModel = [
            'title' => 'model1',
            'link_one_before_id' => 'linkOneBefore1',
            'has_one_before_id' => 'hasOneBefore1',
            'has_one' => [
                'title' => 'hasOne1', 'owner_id' => 'model1'
            ],
            'has_many' => [
                ['title' => 'hasMany1', 'owner_id' => 'model1'],
                ['title' => 'hasMany2', 'owner_id' => 'model1']
            ],
            'link_one' => [
                'title' => 'linkOne1'
            ],
            'link_many' => [
                ['title' => 'linkMany1'],
                ['title' => 'linkMany2']
            ],
            'link_one_before' => [
                'title' => 'linkOneBefore1'
            ],
            'has_one_before' => [
                'title' => 'hasOneBefore1'
            ]
        ];

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedModel, $this->model->getAttributes());
    }

    public function test_create_has_one_recursive()
    {
        $this->save(
            data: [
                'title' => 'model',
                'has_one' => [
                    'title' => 'hasOne',
                    'has_one' => [
                        'title' => 'hasOne_hasOne'
                    ],
                    'has_one_before' => [
                        'title' => 'hasOne_hasOneBefore'
                    ],
                    'has_many' => [
                        ['title' => 'hasOne_hasMany']
                    ],
                    'link_one' => [
                        'id' => 'hasOne_linkOne'
                    ],
                    'link_one_before' => [
                        'id' => 'hasOne_linkOneBefore'
                    ],
                    'link_many' => [
                        ['id' => 'hasOne_linkMany']
                    ]
                ]
            ]
        );

        $expectedInfo = [
            'modelAdd_model',
            'hasOneSaveOwnerToRelated_model',
            'hasOneBeforeAddBeforeOwner_hasOne_hasOneBefore',
            'hasOneBeforeSaveRelatedToOwner_hasOne_hasOneBefore',
            'linkOneBeforeExists_hasOne_linkOneBefore',
            'linkOneBeforeSave_hasOne_linkOneBefore',
            'hasOneAdd_hasOne',
            'hasOneSaveOwnerToRelated_hasOne',
            'hasOneAdd_hasOne_hasOne',
            'hasManySaveOwnerToRelated_hasOne',
            'hasManyAdd_hasOne_hasMany',
            'linkOneExists_hasOne_linkOne',
            'linkOneLink_hasOne_linkOne',
            'linkManyExists_hasOne_linkMany',
            'linkManyLink_hasOne_linkMany'
        ];

        $expectedModel = [
            'title' => 'model',
            'has_one' => [
                'title' => 'hasOne',
                'owner_id' => 'model',
                'link_one_before_id' => 'hasOne_linkOneBefore',
                'has_one_before_id' => 'hasOne_hasOneBefore',
                'has_one' => [
                    'title' => 'hasOne_hasOne', 'owner_id' => 'hasOne'
                ],
                'has_many' => [
                    ['title' => 'hasOne_hasMany', 'owner_id' => 'hasOne'],
                ],
                'link_one' => [
                    'title' => 'hasOne_linkOne'
                ],
                'link_many' => [
                    ['title' => 'hasOne_linkMany'],
                ],
                'link_one_before' => [
                    'title' => 'hasOne_linkOneBefore'
                ],
                'has_one_before' => [
                    'title' => 'hasOne_hasOneBefore'
                ]
            ],
        ];

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedModel, $this->model->getAttributes());
    }

    public function test_create_has_one_before_recursive()
    {
        $this->save(
            data: [
                'title' => 'model',
                'has_one_before' => [
                    'title' => 'hasOneBefore',
                    'has_one' => ['title' => 'hasOneBefore_hasOne'],
                    'has_one_before' => ['title' => 'hasOneBefore_hasOneBefore'],
                    'has_many' => [
                        ['title' => 'hasOneBefore_hasMany']
                    ],
                    'link_one' => ['id' => 'hasOneBefore_linkOne'],
                    'link_one_before' => ['id' => 'hasOneBefore_linkOneBefore'],
                    'link_many' => [
                        ['id' => 'hasOneBefore_linkMany']
                    ]
                ]
            ]
        );

        $expectedInfo = [
            'hasOneBeforeAddBeforeOwner_hasOneBefore_hasOneBefore',
            'hasOneBeforeSaveRelatedToOwner_hasOneBefore_hasOneBefore',
            'linkOneBeforeExists_hasOneBefore_linkOneBefore',
            'linkOneBeforeSave_hasOneBefore_linkOneBefore',

            'hasOneBeforeAddBeforeOwner_hasOneBefore',

            'hasOneSaveOwnerToRelated_hasOneBefore',
            'hasOneAdd_hasOneBefore_hasOne',

            'hasManySaveOwnerToRelated_hasOneBefore',
            'hasManyAdd_hasOneBefore_hasMany',

            'linkOneExists_hasOneBefore_linkOne',
            'linkOneLink_hasOneBefore_linkOne',
            'linkManyExists_hasOneBefore_linkMany',
            'linkManyLink_hasOneBefore_linkMany',

            'hasOneBeforeSaveRelatedToOwner_hasOneBefore',
            'modelAdd_model'
        ];

        $expectedModel = [
            'title' => 'model',
            'has_one_before_id' => 'hasOneBefore',
            'has_one_before' => [
                'title' => 'hasOneBefore',
                'link_one_before_id' => 'hasOneBefore_linkOneBefore',
                'has_one_before_id' => 'hasOneBefore_hasOneBefore',
                'has_one' => [
                    'title' => 'hasOneBefore_hasOne', 'owner_id' => 'hasOneBefore'
                ],
                'has_many' => [
                    ['title' => 'hasOneBefore_hasMany', 'owner_id' => 'hasOneBefore'],
                ],
                'link_one' => [
                    'title' => 'hasOneBefore_linkOne'
                ],
                'link_many' => [
                    ['title' => 'hasOneBefore_linkMany'],
                ],
                'link_one_before' => [
                    'title' => 'hasOneBefore_linkOneBefore'
                ],
                'has_one_before' => [
                    'title' => 'hasOneBefore_hasOneBefore'
                ]
            ],
        ];

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedModel, $this->model->getAttributes());
    }

    public function test_create_has_many_recursive()
    {
        $this->save(
            data: [
                'title' => 'model',
                'has_many' => [
                    [
                        'title' => 'hasMany',
                        'has_one' => ['title' => 'hasMany_hasOne'],
                        'has_one_before' => [
                            'title' => 'hasMany_hasOneBefore'
                        ],
                        'has_many' => [
                            ['title' => 'hasMany_hasMany']
                        ],
                        'link_one' => [
                            'id' => 'hasMany_linkOne'
                        ],
                        'link_one_before' => [
                            'id' => 'hasMany_linkOneBefore'
                        ],
                        'link_many' => [
                            ['id' => 'hasMany_linkMany']
                        ]
                    ]
                ]
            ]
        );

        $expectedInfo = [
            'modelAdd_model',
            'hasManySaveOwnerToRelated_model',

            'hasOneBeforeAddBeforeOwner_hasMany_hasOneBefore',
            'hasOneBeforeSaveRelatedToOwner_hasMany_hasOneBefore',
            'linkOneBeforeExists_hasMany_linkOneBefore',
            'linkOneBeforeSave_hasMany_linkOneBefore',

            'hasManyAdd_hasMany',

            'hasOneSaveOwnerToRelated_hasMany',
            'hasOneAdd_hasMany_hasOne',

            'hasManySaveOwnerToRelated_hasMany',
            'hasManyAdd_hasMany_hasMany',

            'linkOneExists_hasMany_linkOne',
            'linkOneLink_hasMany_linkOne',
            'linkManyExists_hasMany_linkMany',
            'linkManyLink_hasMany_linkMany'
        ];

        $expectedModel = [
            'title' => 'model',
            'has_many' => [
                [
                    'title' => 'hasMany',
                    'owner_id' => 'model',
                    'link_one_before_id' => 'hasMany_linkOneBefore',
                    'has_one_before_id' => 'hasMany_hasOneBefore',
                    'has_one' => [
                        'title' => 'hasMany_hasOne', 'owner_id' => 'hasMany'
                    ],
                    'has_many' => [
                        ['title' => 'hasMany_hasMany', 'owner_id' => 'hasMany'],
                    ],
                    'link_one' => [
                        'title' => 'hasMany_linkOne'
                    ],
                    'link_many' => [
                        ['title' => 'hasMany_linkMany'],
                    ],
                    'link_one_before' => [
                        'title' => 'hasMany_linkOneBefore'
                    ],
                    'has_one_before' => [
                        'title' => 'hasMany_hasOneBefore'
                    ]
                ]
            ],
        ];

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedModel, $this->model->getAttributes());
    }

    public function test_update()
    {
        $this->save(
            params: ['id' => 'model1'],
            data: [
                'title' => 'model1',
                'has_one' => [
                    'title' => 'hasOne1'
                ],
                'has_one_before' => [
                    'title' => 'hasOneBefore1'
                ],
                'has_many' => [
                    ['title' => 'hasMany1'],
                    ['title' => 'hasMany2']
                ],
                'link_one' => [
                    'id' => 'linkOne1'
                ],
                'link_one_before' => [
                    'id' => 'linkOneBefore1'
                ],
                'link_many' => [
                    ['id' => 'linkMany1'],
                    ['id' => 'linkMany2']
                ]
            ]
        );

        $expectedInfo = [
            'modelGet_model1',

            'hasOneBeforeGet_model1',
            'hasOneBeforeAdd_hasOneBefore1',

            'hasOneBeforeSaveRelatedToOwner_hasOneBefore1',
            'linkOneBeforeExists_linkOneBefore1',
            'linkOneBeforeSave_linkOneBefore1',

            'modelUpdate_model1',

            'hasOneSaveOwnerToRelated_model1',
            'hasOneGet_model1',
            'hasOneAdd_hasOne1',

            'hasManySaveOwnerToRelated_model1',
            'hasManyGet_model1',
            'hasManyAdd_hasMany1',
            'hasManyAdd_hasMany2',

            'linkOneExists_linkOne1',
            'linkOneGet_model1',
            'linkOneLink_linkOne1',

            'linkManyGet_model1',
            'linkManyExists_linkMany1',
            'linkManyLink_linkMany1',
            'linkManyExists_linkMany2',
            'linkManyLink_linkMany2'
        ];

        $expectedModel = [
            'title' => 'model1',
            'link_one_before_id' => 'linkOneBefore1',
            'has_one_before_id' => 'hasOneBefore1',
            'has_one' => [
                'title' => 'hasOne1', 'owner_id' => 'model1'
            ],
            'has_many' => [
                ['title' => 'hasMany1', 'owner_id' => 'model1'],
                ['title' => 'hasMany2', 'owner_id' => 'model1']
            ],
            'link_one' => [
                'title' => 'linkOne1'
            ],
            'link_many' => [
                ['title' => 'linkMany1'],
                ['title' => 'linkMany2']
            ],
            'link_one_before' => [
                'title' => 'linkOneBefore1'
            ],
            'has_one_before' => [
                'title' => 'hasOneBefore1'
            ]
        ];

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedModel, $this->model->getAttributes());
    }

    public function test_update_has_one_recursive()
    {
        $this->save(
            params: ['id' => 'model'],
            data: [
                'title' => 'model',
                'has_one' => [
                    'title' => 'hasOne',
                    'has_one' => [
                        'title' => 'hasOne_hasOne'
                    ],
                    'has_one_before' => [
                        'title' => 'hasOne_hasOneBefore'
                    ],
                    'has_many' => [
                        ['title' => 'hasOne_hasMany']
                    ],
                    'link_one' => [
                        'id' => 'hasOne_linkOne'
                    ],
                    'link_one_before' => [
                        'id' => 'hasOne_linkOneBefore'
                    ],
                    'link_many' => [
                        ['id' => 'hasOne_linkMany']
                    ]
                ]
            ]
        );

        $expectedInfo = [
            'modelGet_model',
            'modelUpdate_model',

            'hasOneSaveOwnerToRelated_model',
            'hasOneGet_model',

            'hasOneBeforeAddBeforeOwner_hasOne_hasOneBefore',
            'hasOneBeforeSaveRelatedToOwner_hasOne_hasOneBefore',
            'linkOneBeforeExists_hasOne_linkOneBefore',
            'linkOneBeforeSave_hasOne_linkOneBefore',

            'hasOneAdd_hasOne',
            'hasOneSaveOwnerToRelated_hasOne',
            'hasOneAdd_hasOne_hasOne',
            'hasManySaveOwnerToRelated_hasOne',
            'hasManyAdd_hasOne_hasMany',
            'linkOneExists_hasOne_linkOne',
            'linkOneLink_hasOne_linkOne',
            'linkManyExists_hasOne_linkMany',
            'linkManyLink_hasOne_linkMany'
        ];

        $expectedModel = [
            'title' => 'model',
            'has_one' => [
                'title' => 'hasOne',
                'owner_id' => 'model',
                'link_one_before_id' => 'hasOne_linkOneBefore',
                'has_one_before_id' => 'hasOne_hasOneBefore',
                'has_one' => [
                    'title' => 'hasOne_hasOne', 'owner_id' => 'hasOne'
                ],
                'has_many' => [
                    ['title' => 'hasOne_hasMany', 'owner_id' => 'hasOne'],
                ],
                'link_one' => [
                    'title' => 'hasOne_linkOne'
                ],
                'link_many' => [
                    ['title' => 'hasOne_linkMany'],
                ],
                'link_one_before' => [
                    'title' => 'hasOne_linkOneBefore'
                ],
                'has_one_before' => [
                    'title' => 'hasOne_hasOneBefore'
                ]
            ],
        ];

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedModel, $this->model->getAttributes());
    }

    public function test_update_has_one_before_recursive()
    {
        $this->save(
            params: ['id' => 'model'],
            data: [
                'title' => 'model',
                'has_one_before' => [
                    'title' => 'hasOneBefore',
                    'has_one' => ['title' => 'hasOneBefore_hasOne'],
                    'has_one_before' => ['title' => 'hasOneBefore_hasOneBefore'],
                    'has_many' => [
                        ['title' => 'hasOneBefore_hasMany']
                    ],
                    'link_one' => ['id' => 'hasOneBefore_linkOne'],
                    'link_one_before' => ['id' => 'hasOneBefore_linkOneBefore'],
                    'link_many' => [
                        ['id' => 'hasOneBefore_linkMany']
                    ]
                ]
            ]
        );

        $expectedInfo = [
            'modelGet_model',

            'hasOneBeforeGet_model',

            'hasOneBeforeAddBeforeOwner_hasOneBefore_hasOneBefore',
            'hasOneBeforeSaveRelatedToOwner_hasOneBefore_hasOneBefore',
            'linkOneBeforeExists_hasOneBefore_linkOneBefore',
            'linkOneBeforeSave_hasOneBefore_linkOneBefore',

            'hasOneBeforeAdd_hasOneBefore',

            'hasOneSaveOwnerToRelated_hasOneBefore',
            'hasOneAdd_hasOneBefore_hasOne',

            'hasManySaveOwnerToRelated_hasOneBefore',
            'hasManyAdd_hasOneBefore_hasMany',

            'linkOneExists_hasOneBefore_linkOne',
            'linkOneLink_hasOneBefore_linkOne',
            'linkManyExists_hasOneBefore_linkMany',
            'linkManyLink_hasOneBefore_linkMany',

            'hasOneBeforeSaveRelatedToOwner_hasOneBefore',
            'modelUpdate_model'
        ];

        $expectedModel = [
            'title' => 'model',
            'has_one_before_id' => 'hasOneBefore',
            'has_one_before' => [
                'title' => 'hasOneBefore',
                'link_one_before_id' => 'hasOneBefore_linkOneBefore',
                'has_one_before_id' => 'hasOneBefore_hasOneBefore',
                'has_one' => [
                    'title' => 'hasOneBefore_hasOne', 'owner_id' => 'hasOneBefore'
                ],
                'has_many' => [
                    ['title' => 'hasOneBefore_hasMany', 'owner_id' => 'hasOneBefore'],
                ],
                'link_one' => [
                    'title' => 'hasOneBefore_linkOne'
                ],
                'link_many' => [
                    ['title' => 'hasOneBefore_linkMany'],
                ],
                'link_one_before' => [
                    'title' => 'hasOneBefore_linkOneBefore'
                ],
                'has_one_before' => [
                    'title' => 'hasOneBefore_hasOneBefore'
                ]
            ],
        ];

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedModel, $this->model->getAttributes());
    }

    public function test_update_has_many_recursive()
    {
        $this->save(
            params: ['id' => 'model'],
            data: [
                'title' => 'model',
                'has_many' => [
                    [
                        'title' => 'hasMany',
                        'has_one' => ['title' => 'hasMany_hasOne'],
                        'has_one_before' => [
                            'title' => 'hasMany_hasOneBefore'
                        ],
                        'has_many' => [
                            ['title' => 'hasMany_hasMany']
                        ],
                        'link_one' => [
                            'id' => 'hasMany_linkOne'
                        ],
                        'link_one_before' => [
                            'id' => 'hasMany_linkOneBefore'
                        ],
                        'link_many' => [
                            ['id' => 'hasMany_linkMany']
                        ]
                    ]
                ]
            ]
        );

        $expectedInfo = [
            'modelGet_model',
            'modelUpdate_model',

            'hasManySaveOwnerToRelated_model',
            'hasManyGet_model',

            'hasOneBeforeAddBeforeOwner_hasMany_hasOneBefore',
            'hasOneBeforeSaveRelatedToOwner_hasMany_hasOneBefore',
            'linkOneBeforeExists_hasMany_linkOneBefore',
            'linkOneBeforeSave_hasMany_linkOneBefore',

            'hasManyAdd_hasMany',

            'hasOneSaveOwnerToRelated_hasMany',
            'hasOneAdd_hasMany_hasOne',

            'hasManySaveOwnerToRelated_hasMany',
            'hasManyAdd_hasMany_hasMany',

            'linkOneExists_hasMany_linkOne',
            'linkOneLink_hasMany_linkOne',
            'linkManyExists_hasMany_linkMany',
            'linkManyLink_hasMany_linkMany'
        ];

        $expectedModel = [
            'title' => 'model',
            'has_many' => [
                [
                    'title' => 'hasMany',
                    'owner_id' => 'model',
                    'link_one_before_id' => 'hasMany_linkOneBefore',
                    'has_one_before_id' => 'hasMany_hasOneBefore',
                    'has_one' => [
                        'title' => 'hasMany_hasOne', 'owner_id' => 'hasMany'
                    ],
                    'has_many' => [
                        ['title' => 'hasMany_hasMany', 'owner_id' => 'hasMany'],
                    ],
                    'link_one' => [
                        'title' => 'hasMany_linkOne'
                    ],
                    'link_many' => [
                        ['title' => 'hasMany_linkMany'],
                    ],
                    'link_one_before' => [
                        'title' => 'hasMany_linkOneBefore'
                    ],
                    'has_one_before' => [
                        'title' => 'hasMany_hasOneBefore'
                    ]
                ]
            ],
        ];

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
        $this->assertEquals($expectedModel, $this->model->getAttributes());
    }

    private function getApi(): Api
    {
        return $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class)
                    ->attribute('has_one_before_id', StringAttribute::class)
                    ->attribute('link_one_before_id', StringAttribute::class)
                    ->attribute('owner_id', StringAttribute::class)
                    ->relation('has_one', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->saveOwnerToRelated(function (string $id, string $typeName) {
                                    $this->testWatcher->info('hasOneSaveOwnerToRelated_' . $id);
                                    return ['owner_id' => $id];
                                })
                                ->get(function (Model $owner) {
                                    $this->testWatcher->info('hasOneGet_' . $owner->id);
                                })
                                ->add(function (Model $owner, $typeName, $saveFields) {
                                    $this->testWatcher->info('hasOneAdd_' . $saveFields['title']);
                                    $saveFields['id'] = $saveFields['title'];
                                    $related = Model::fromSingle($typeName, $saveFields);
                                    $owner->has_one = $related;
                                    return $related;
                                })
                                ->update(function (Model $owner, Model $related, $saveFields) {
                                    $this->testWatcher->info('hasOneUpdate');
                                })
                                ->delete(function () {
                                    $this->testWatcher->info('hasOneDelete');
                                });
                        });
                    })
                    ->relation('has_one_before', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->saveRelatedToOwner(function (?string $id, ?string $typeName) {
                                    $this->testWatcher->info('hasOneBeforeSaveRelatedToOwner_' . $id);
                                    return ['has_one_before_id' => $id];
                                })
                                ->get(function (Model $owner) {
                                    $this->testWatcher->info('hasOneBeforeGet_' . $owner->id);
                                })
                                ->addBeforeOwner(function ($typeName, $saveFields) {
                                    $this->testWatcher->info('hasOneBeforeAddBeforeOwner_' . $saveFields['title']);
                                    $saveFields['id'] = $saveFields['title'];
                                    $related = Model::fromSingle($typeName, $saveFields);
                                    $this->modelIdMap[$saveFields['title']] = $related;
                                    return $related;
                                })
                                ->add(function (Model $owner, $typeName, $saveFields) {
                                    $this->testWatcher->info('hasOneBeforeAdd_' . $saveFields['title']);
                                    $saveFields['id'] = $saveFields['title'];
                                    $related = Model::fromSingle($typeName, $saveFields);
                                    $this->modelIdMap[$saveFields['title']] = $related;
                                    return $related;
                                })
                                ->update(function (Model $owner, Model $related, $saveFields) {
                                    $this->testWatcher->info('hasOneBeforeUpdate');
                                })
                                ->delete(function () {
                                    $this->testWatcher->info('hasOneBeforeDelete');
                                });
                        });
                    })
                    ->relation('has_many', Type::list(T('TYPE')), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasManyResolver $r) {
                            $r
                                ->saveOwnerToRelated(function (string $id, string $typeName) {
                                    $this->testWatcher->info('hasManySaveOwnerToRelated_' . $id);
                                    return ['owner_id' => $id];
                                })
                                ->get(function (ModelInterface $owner) {
                                    $this->testWatcher->info('hasManyGet_' . $owner->id);
                                    return [];
                                })
                                ->add(function (Model $owner, $typeName, $saveFields) {
                                    $this->testWatcher->info('hasManyAdd_' . $saveFields['title']);
                                    $saveFields['id'] = $saveFields['title'];
                                    $related = Model::fromSingle($typeName, $saveFields);
                                    $owner->has_many[] = $related;
                                    return $related;
                                })
                                ->update(function (Model $owner, Model $related, $saveFields) {
                                    $this->testWatcher->info('hasManyUpdate');
                                })
                                ->delete(function () {
                                    $this->testWatcher->info('hasManyDelete');
                                });
                        });
                    })
                    ->relation('link_one_before', Type::link(T('TYPE')), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationLinkOneResolver $r) {
                            $r
                                ->saveRelatedToOwner(function (?string $id, ?string $typeName) {
                                    $this->testWatcher->info('linkOneBeforeSave_' . $id);
                                    return ['link_one_before_id' => $id];
                                })
                                ->exists(function (string $id, string $typeName) {
                                    $this->testWatcher->info('linkOneBeforeExists_' . $id);
                                    return true;
                                });
                        });
                    })
                    ->relation('link_one', Type::link(T('TYPE')), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationLinkOneResolver $r) {
                            $r
                                ->get(function (ModelInterface $owner) {
                                    $this->testWatcher->info('linkOneGet_' . $owner->id);
                                })
                                ->exists(function (string $id, string $typeName) {
                                    $this->testWatcher->info('linkOneExists_' . $id);
                                    return true;
                                })
                                ->link(function (ModelInterface $owner, string $id, string $typeName) {
                                    $this->testWatcher->info('linkOneLink_' . $id);
                                    $owner->link_one = Model::fromSingle($typeName, ['title' => $id]);
                                })
                                ->unlink(function (ModelInterface $owner, ModelInterface $modelToUnlink) {
                                    $this->testWatcher->info('linkOneUnlink');
                                });
                        });
                    })
                    ->relation('link_many', Type::list(Type::link(T('TYPE'))), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationLinkManyResolver $r) {
                            $r
                                ->get(function (ModelInterface $owner) {
                                    $this->testWatcher->info('linkManyGet_' . $owner->id);
                                    return [];
                                })
                                ->exists(function (string $id, string $typeName) {
                                    $this->testWatcher->info('linkManyExists_' . $id);
                                    return true;
                                })
                                ->link(function (ModelInterface $owner, string $id, string $typeName) {
                                    $this->testWatcher->info('linkManyLink_' . $id);
                                    $owner->link_many[] = Model::fromSingle($typeName, ['title' => $id]);
                                })
                                ->unlink(function (ModelInterface $owner, ModelInterface $modelToUnlink) {
                                    $this->testWatcher->info('linkManyUnlink');
                                });
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) {
                $action
                    ->resolve(function (MutationActionModelResolver $r) {
                        $r
                            ->get(function (string $id, string $typeName) {
                                $this->testWatcher->info('modelGet_' . $id);
                                $this->model->id = $id;
                                return $this->model;
                            })
                            ->add(function (string $typeName, array $saveFields) {
                                $this->testWatcher->info('modelAdd_' . $saveFields['title']);
                                $saveFields['id'] = $saveFields['title'];
                                $this->model->saveFields($saveFields);
                                return $this->model;
                            })
                            ->update(function (ModelInterface $model, array $saveFields) use ($r) {
                                $this->testWatcher->info('modelUpdate_' . $model->id);
                                $this->model->saveFields($saveFields);
                            })
                            ->delete(function () {
                                $this->testWatcher->info('modelDelete');
                            });
                    });
            }
        );
    }

    private function save(array $data = [], array $params = []): array
    {
        $result = $this->request($this->getApi(), $data, $params);
        $this->model->typeOfModel = $this->getTypeByName('TYPE'); // get and set not until resources are initialized in request()

        $this->model->modelIdMap = $this->modelIdMap;
        return $result;
    }

    private function getTypeByName(string $typeName): Type
    {
        $TypeClass = $this->container->get(TypeClassMap::class)->get($typeName) ?? Type::class;
        return $this->container->get($TypeClass);
    }
}

class Model extends BaseModel
{
    public ?Type $typeOfModel = null;
    public array $modelIdMap = [];

    public function saveFields(array $saveFields)
    {
        foreach ($saveFields as $key => $value) {
            $this->$key = $value;
        }
    }

    public function getAttributes(?Type $type = null, array $modelIdMap = null): array
    {
        $type ??= $this->typeOfModel;
        $modelIdMap ??= $this->modelIdMap;

        $attributes = [];

        $fields = $type->getUpdateFields()->getEntries();
        foreach ($fields as $name => $field) {
            if ($field instanceof Attribute) {
                if (isset($this->$name)) {
                    if ($name === 'link_one_before_id') {
                        $attributes['link_one_before'] = [
                            'title' => $this->$name
                        ];
                    } elseif ($name === 'has_one_before_id') {
                        $attributes['has_one_before'] = $modelIdMap[$this->$name]->getAttributes($type, $modelIdMap);
                    }

                    $attributes[$name] = $this->$name;
                }
            } else {
                $relation = $type->getUpdateRelation($name);
                if (isset($this->$name)) {
                    if ($relation->getRelatedType()->isList()) {
                        $attributes[$name] = [];
                        foreach ($this->$name as $related) {
                            $attributes[$name][] = $related->getAttributes($type, $modelIdMap);
                        }
                    } else {
                        $attributes[$name] = $this->$name->getAttributes($type, $modelIdMap);
                    }
                } elseif (isset($this->$name)) {
                    if ($relation->getRelatedType()->isList()) {
                        $attributes[$name] = [];
                    } else {
                        $attributes[$name] = null;
                    }
                }
            }
        }
        return $attributes;
    }
}
