<?php

namespace Afeefa\ApiResources\Tests\Api\Schema;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\createApiWithSingleResource;

use function Afeefa\ApiResources\Test\createApiWithSingleType;
use function Afeefa\ApiResources\Test\T;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Validator\Validators\StringValidator;
use Closure;

class SchemaTypeTest extends ApiResourcesTest
{
    public function test_simple()
    {
        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class)
                    ->attribute('name', StringAttribute::class)
                    ->relation('related_type', T('Test.Type'));
            }
        );

        $schema = $api->toSchemaJson();

        // debug_dump($schema);

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'title' => [
                        'type' => 'Afeefa.StringAttribute'
                    ],
                    'name' => [
                        'type' => 'Afeefa.StringAttribute'
                    ],
                    'related_type' => [
                        'type' => 'Afeefa.Relation',
                        'related_type' => [
                            'type' => 'Test.Type'
                        ]
                    ]
                ],
                'update_fields' => [],
                'create_fields' => []
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);

        $this->assertEquals([], $schema['validators']);
    }

    public function test_validator()
    {
        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (StringAttribute $attribute) {
                        $attribute->validate(function (StringValidator $v) {
                            $v->min(10);
                        });
                    });
            }
        );

        $schema = $api->toSchemaJson();

        // debug_dump($schema);

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'title' => [
                        'type' => 'Afeefa.StringAttribute',
                        'validator' => [
                            'type' => 'Afeefa.StringValidator',
                            'params' => [
                                'min' => 10
                            ]
                        ]
                    ]
                ],
                'update_fields' => [],
                'create_fields' => []
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);

        $this->assertEquals(['Afeefa.StringValidator'], array_keys($schema['validators']));
        $this->assertEquals(['rules'], array_keys($schema['validators']['Afeefa.StringValidator']));
    }

    public function test_required()
    {
        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (StringAttribute $attribute) {
                        $attribute->required();
                    });
            }
        );

        $schema = $api->toSchemaJson();

        // debug_dump($schema);

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'title' => [
                        'type' => 'Afeefa.StringAttribute',
                        'required' => true
                    ]
                ],
                'update_fields' => [],
                'create_fields' => []
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);
    }

    public function test_update_fields()
    {
        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', StringAttribute::class);
            },
            function (FieldBag $fields) {
                $fields
                    ->attribute('title_update', StringAttribute::class)
                    ->attribute('title_update_create', StringAttribute::class);
            },
            function (FieldBag $fields, FieldBag $updateFields) {
                $fields
                    ->from($updateFields, 'title_update_create')
                    ->attribute('title_create', StringAttribute::class);
            }
        );

        $schema = $api->toSchemaJson();

        // debug_dump($schema);

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'title' => [
                        'type' => 'Afeefa.StringAttribute'
                    ]
                ],
                'update_fields' => [
                    'title_update' => [
                        'type' => 'Afeefa.StringAttribute'
                    ],
                    'title_update_create' => [
                        'type' => 'Afeefa.StringAttribute'
                    ]
                ],
                'create_fields' => [
                    'title_update_create' => [
                        'type' => 'Afeefa.StringAttribute'
                    ],
                    'title_create' => [
                        'type' => 'Afeefa.StringAttribute'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);
    }

    public function test_attribute_default_value()
    {
        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (StringAttribute $a) {
                        $a->default('title_default');
                    });
            },
            function (FieldBag $fields) {
                $fields
                    ->attribute('title_update', function (StringAttribute $a) {
                        $a->default('title_update_default');
                    })
                    ->attribute('title_update_create', function (StringAttribute $a) {
                        $a->default('title_update_create_default');
                    })
                    ->attribute('title_update_create2', function (StringAttribute $a) {
                        $a->default('title_update_create2_default');
                    });
            },
            function (FieldBag $fields, FieldBag $updateFields) {
                $fields
                    ->from($updateFields, 'title_update_create', function (StringAttribute $a) {
                        $a->default('title_update_create_default_create');
                    })
                    ->from($updateFields, 'title_update_create2')
                    ->attribute('title_create', function (StringAttribute $a) {
                        $a->default('title_create_default');
                    });
            }
        );

        $schema = $api->toSchemaJson();

        // debug_dump($schema);

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'title' => [
                        'type' => 'Afeefa.StringAttribute'
                    ]
                ],
                'update_fields' => [
                    'title_update' => [
                        'type' => 'Afeefa.StringAttribute',
                        'default' => 'title_update_default'
                    ],
                    'title_update_create' => [
                        'type' => 'Afeefa.StringAttribute',
                        'default' => 'title_update_create_default'
                    ],
                    'title_update_create2' => [
                        'type' => 'Afeefa.StringAttribute',
                        'default' => 'title_update_create2_default'
                    ]
                ],
                'create_fields' => [
                    'title_update_create' => [
                        'type' => 'Afeefa.StringAttribute',
                        'default' => 'title_update_create_default_create'
                    ],
                    'title_update_create2' => [
                        'type' => 'Afeefa.StringAttribute',
                        'default' => 'title_update_create2_default'
                    ],
                    'title_create' => [
                        'type' => 'Afeefa.StringAttribute',
                        'default' => 'title_create_default'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);
    }

    public function test_type_not_in_action()
    {
        $this->typeBuilder()->type('Test.Type')->get();

        $api = createApiWithSingleResource();

        $schema = $api->toSchemaJson();

        $this->assertEquals([], array_keys($schema['types']));
    }

    public function test_type_in_action_input()
    {
        $this->typeBuilder()->type('Test.Type')->get();

        $api = createApiWithSingleResource(function (Closure $addAction, Closure $addMutation) {
            $addMutation('type', T('Test.Type'), function (Action $action) {
                $action->resolve(function () {
                });
            });
        });

        $schema = $api->toSchemaJson();

        $this->assertEquals(['Test.Type'], array_keys($schema['types']));
    }

    public function test_type_in_action_response()
    {
        $this->typeBuilder()->type('Test.Type')->get();

        $api = createApiWithSingleResource(function (Closure $addAction) {
            $addAction('type', T('Test.Type'), function (Action $action) {
                $action
                    ->response(T('Test.Type'))
                    ->resolve(function () {
                    });
            });
        });

        $schema = $api->toSchemaJson();

        $this->assertEquals(['Test.Type'], array_keys($schema['types']));
    }

    public function test_type_in_relation()
    {
        $this->typeBuilder()->type('Test.Type2')->get();

        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->relation('other_type', T('Test.Type2'));
            }
        );

        $schema = $api->toSchemaJson();

        $this->assertEquals(['Test.Type', 'Test.Type2'], array_keys($schema['types']));
    }

    public function test_add_with_missing_type()
    {
        $this->expectException(NotATypeException::class);
        $this->expectExceptionMessage('Value for response $TypeClassOrClasses is not a type or a list of types.');

        $type = $this->typeBuilder()->type()->get();

        $api = createApiWithSingleResource(function (Closure $addAction) use ($type) {
            $addAction('type', null, function (Action $action) use ($type) {
                $action
                    ->resolve(function () {
                    });
            });
        });

        $api->toSchemaJson();
    }

    public function test_relation()
    {
        $this->typeBuilder()->type('Test.Type2')->get();

        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->relation('other_type', T('Test.Type2'))
                    ->relation('other_type2', [T('Test.Type2')]); // single array element
            }
        );

        $schema = $api->toSchemaJson();

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'other_type' => [
                        'type' => 'Afeefa.Relation',
                        'related_type' => [
                            'type' => 'Test.Type2'
                        ]
                    ],
                    'other_type2' => [
                        'type' => 'Afeefa.Relation',
                        'related_type' => [
                            'type' => 'Test.Type2'
                        ]
                    ]
                ],
                'update_fields' => [],
                'create_fields' => []
            ],
            'Test.Type2' => [
                'translations' => [],
                'fields' => [],
                'update_fields' => [],
                'create_fields' => []
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);
    }

    public function test_relation_list()
    {
        $this->typeBuilder()->type('Test.Type2')->get();

        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->relation('other_types', Type::list(T('Test.Type2')))
                    ->relation('other_types2', Type::list([T('Test.Type2')])); // single array element
            }
        );

        $schema = $api->toSchemaJson();

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'other_types' => [
                        'type' => 'Afeefa.Relation',
                        'related_type' => [
                            'type' => 'Test.Type2',
                            'list' => true
                        ]
                    ],
                    'other_types2' => [
                        'type' => 'Afeefa.Relation',
                        'related_type' => [
                            'type' => 'Test.Type2',
                            'list' => true
                        ]
                    ]
                ],
                'update_fields' => [],
                'create_fields' => []
            ],
            'Test.Type2' => [
                'translations' => [],
                'fields' => [],
                'update_fields' => [],
                'create_fields' => []
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);
    }

    public function test_relation_mixed()
    {
        $this->typeBuilder()->type('Test.Type2')->get();
        $this->typeBuilder()->type('Test.Type3')->get();

        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->relation('other_type', [T('Test.Type2'), T('Test.Type3')])
                    ->relation('other_types', Type::list([T('Test.Type2'), T('Test.Type3')]));
            }
        );

        $schema = $api->toSchemaJson();

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'other_type' => [
                        'type' => 'Afeefa.Relation',
                        'related_type' => [
                            'types' => ['Test.Type2', 'Test.Type3']
                        ]
                    ],
                    'other_types' => [
                        'type' => 'Afeefa.Relation',
                        'related_type' => [
                            'types' => ['Test.Type2', 'Test.Type3'],
                            'list' => true
                        ]
                    ]
                ],
                'update_fields' => [],
                'create_fields' => []
            ],
            'Test.Type2' => [
                'translations' => [],
                'fields' => [],
                'update_fields' => [],
                'create_fields' => []
            ],
            'Test.Type3' => [
                'translations' => [],
                'fields' => [],
                'update_fields' => [],
                'create_fields' => []
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);
    }
}
