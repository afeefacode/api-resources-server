<?php

namespace Afeefa\ApiResources\Tests\Resolver;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\MutationActionModelResolver;
use Afeefa\ApiResources\Resolver\MutationRelationHasOneResolver;
use Afeefa\ApiResources\Test\MutationTest;
use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Validator\ValidationFailedException;
use Afeefa\ApiResources\Validator\Validators\StringValidator;
use Afeefa\ApiResources\Validator\Validators\TextValidator;

class MutationValidatorTest extends MutationTest
{
    protected Model $model;
    protected array $modelIdMap = [];

    protected function setUp(): void
    {
        parent::setup();

        $this->model = Model::fromSingle('TYPE');
        $this->modelIdMap = [];
    }

    public function test_validate()
    {
        $this->save(
            data: [
                'title' => 'a',
                'sub' => [
                    'title' => 'b',
                    'sub' => [
                        'title' => 'c',
                    ]
                ]
            ]
        );

        $expectedFields = [
            ['title' => 'a'],
            ['title' => 'b'],
            ['title' => 'c']
        ];

        $this->assertEquals($expectedFields, $this->testWatcher->saveFields);

        $expectedInfo = [
            ['a'],
            ['b'],
            ['c']
        ];

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
    }

    /**
     * @dataProvider missingRequiredFieldsProvider
     */
    public function test_required($data)
    {
        $this->expectException(ValidationFailedException::class);
        $this->expectExceptionMessage('Field title is required but not given.');

        $this->save(
            data: $data
        );

        $expectedFields = [
            ['title' => 'a'],
            ['title' => 'b'],
            ['title' => 'c']
        ];

        $this->assertEquals($expectedFields, $this->testWatcher->saveFields);

        $expectedInfo = [
            ['a'],
            ['b'],
            ['c']
        ];

        $this->assertEquals($expectedInfo, $this->testWatcher->info);
    }

    public function missingRequiredFieldsProvider()
    {
        return [
            [
                [
                    'title' => 'a',
                    'sub' => [
                        'title' => 'b',
                        'sub' => [
                        ]
                    ]
                ]
            ],
            [
                [
                    'title' => 'a',
                    'sub' => [
                        'sub' => [
                            'title' => 'c',
                        ]
                    ]
                ]
            ],
            [
                [
                    'sub' => [
                        'title' => 'b',
                        'sub' => [
                            'title' => 'c',
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * @dataProvider sanitizeDataProvider
     */
    public function test_sanitize($validate, $values, $expectedInfo)
    {
        $this->save(
            data: [
                'title' => $values[0],
                'sub' => [
                    'title' => $values[1],
                    'sub' => [
                        'title' => $values[2],
                        'sub' => [
                            'title' => $values[3],
                        ]
                    ]
                ]
            ],
            validate: $validate
        );

        $expectedFields = array_map(function ($info) {
            return ['title' => $info[0]];
        }, $expectedInfo);

        $this->assertEquals($expectedFields, $this->testWatcher->saveFields);
        $this->assertSame($expectedInfo, $this->testWatcher->info);
    }

    public function sanitizeDataProvider()
    {
        return [
            [
                StringValidator::class,
                [
                    ' a   a    ', ' b', 'c ', ''
                ],
                [
                    ['a a'], ['b'], ['c'], [null]
                ]
            ],
            [
                TextValidator::class,
                [
                    ' a   a    ', ' b', 'c ', ''
                ],
                [
                    ['a   a'], ['b'], ['c'], [null]
                ]
            ],
            [
                function (StringValidator $v) {
                    $v
                        ->trim(false)
                        ->emptyNull(false)
                        ->collapseWhite(false);
                },
                [
                    ' a   a    ', ' b', 'c ', ''
                ],
                [
                    [' a   a    '], [' b'], ['c '], ['']
                ]
            ]
        ];
    }

    private function getApi($validatorOrCallback = null): Api
    {
        return $this->createApiWithUpdateTypeAndMutation(
            function (FieldBag $fields) use ($validatorOrCallback) {
                $fields
                    ->string('title', function (StringAttribute $a) use ($validatorOrCallback) {
                        $a->required();

                        if ($validatorOrCallback) {
                            $a->validate($validatorOrCallback);
                        }
                    })
                    ->hasOne('sub', T('TYPE'), function (Relation $relation) {
                        $relation->resolve(function (MutationRelationHasOneResolver $r) {
                            $r
                                ->get(fn () => null)
                                ->add(function (Model $owner, $typeName, $saveFields) {
                                    $this->testWatcher->info(array_values($saveFields));
                                    $this->testWatcher->saveFields($saveFields);
                                    return Model::fromSingle($typeName, $saveFields);
                                })
                                ->update(fn () => null)
                                ->delete(fn () => null);
                        });
                    });
            },
            fn () => T('TYPE'),
            function (Action $action) {
                $action
                    ->resolve(function (MutationActionModelResolver $r) {
                        $r
                            ->get(fn () => null)
                            ->add(function (string $typeName, array $saveFields) {
                                $this->testWatcher->info(array_values($saveFields));
                                $this->testWatcher->saveFields($saveFields);
                                return Model::fromSingle($typeName, $saveFields);
                            })
                            ->update(fn () => null)
                            ->delete(fn () => null);
                    });
            }
        );
    }

    private function save(array $data = [], $validate = null): array
    {
        return $this->request($this->getApi($validate), $data);
    }
}
