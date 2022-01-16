<?php

namespace Afeefa\ApiResources\Tests\Api\Schema;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\HasOneRelation;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\createApiWithSingleResource;

use function Afeefa\ApiResources\Test\createApiWithSingleType;
use function Afeefa\ApiResources\Test\T;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Validator\Validators\VarcharValidator;
use Closure;

class SchemaTypeTest extends ApiResourcesTest
{
    public function test_simple()
    {
        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->attribute('name', VarcharAttribute::class)
                    ->relation('related_type', T('Test.Type'), HasOneRelation::class);
            }
        );

        $schema = $api->toSchemaJson();

        // debug_dump($schema);

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'title' => [
                        'type' => 'Afeefa.VarcharAttribute'
                    ],
                    'name' => [
                        'type' => 'Afeefa.VarcharAttribute'
                    ],
                    'related_type' => [
                        'type' => 'Afeefa.HasOneRelation',
                        'related_type' => [
                            'type' => 'Test.Type'
                        ]
                    ]
                ]
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
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->validate(function (VarcharValidator $v) {
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
                        'type' => 'Afeefa.VarcharAttribute',
                        'validator' => [
                            'type' => 'Afeefa.VarcharValidator',
                            'params' => [
                                'min' => 10
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);

        $this->assertEquals(['Afeefa.VarcharValidator'], array_keys($schema['validators']));
        $this->assertEquals(['rules'], array_keys($schema['validators']['Afeefa.VarcharValidator']));
    }

    public function test_required()
    {
        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (VarcharAttribute $attribute) {
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
                        'type' => 'Afeefa.VarcharAttribute',
                        'required' => true
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);
    }

    public function test_allowed_fields()
    {
        $api = createApiWithSingleType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', VarcharAttribute::class)
                    ->attribute('title_allowed', VarcharAttribute::class);
                $fields->allow(['title_allowed']);
            }
        );

        $schema = $api->toSchemaJson();

        // debug_dump($schema);

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'title_allowed' => [
                        'type' => 'Afeefa.VarcharAttribute'
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

        $api = createApiWithSingleResource(function (Closure $addAction) {
            $addAction('type', function (Action $action) {
                $action
                    ->input(T('Test.Type'))
                    ->response(T('Test.Type'))
                    ->resolve(function () {
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
            $addAction('type', function (Action $action) {
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
                    ->relation('other_type', T('Test.Type2'), HasOneRelation::class);
            }
        );

        $schema = $api->toSchemaJson();

        $this->assertEquals(['Test.Type', 'Test.Type2'], array_keys($schema['types']));
    }

    public function test_add_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestType@anonymous/');

        $type = $this->typeBuilder()->type()->get();

        $api = createApiWithSingleResource(function (Closure $addAction) use ($type) {
            $addAction('type', function (Action $action) use ($type) {
                $action
                    ->response($type::class)
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
                    ->relation('other_type', T('Test.Type2'), HasOneRelation::class)
                    ->relation('other_type2', [T('Test.Type2')], HasOneRelation::class); // single array element
            }
        );

        $schema = $api->toSchemaJson();

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'other_type' => [
                        'type' => 'Afeefa.HasOneRelation',
                        'related_type' => [
                            'type' => 'Test.Type2'
                        ]
                    ],
                    'other_type2' => [
                        'type' => 'Afeefa.HasOneRelation',
                        'related_type' => [
                            'type' => 'Test.Type2'
                        ]
                    ]
                ]
            ],
            'Test.Type2' => [
                'translations' => [],
                'fields' => []
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
                    ->relation('other_types', Type::list(T('Test.Type2')), HasOneRelation::class)
                    ->relation('other_types2', Type::list([T('Test.Type2')]), HasOneRelation::class); // single array element
            }
        );

        $schema = $api->toSchemaJson();

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'other_types' => [
                        'type' => 'Afeefa.HasOneRelation',
                        'related_type' => [
                            'type' => 'Test.Type2',
                            'list' => true
                        ]
                    ],
                    'other_types2' => [
                        'type' => 'Afeefa.HasOneRelation',
                        'related_type' => [
                            'type' => 'Test.Type2',
                            'list' => true
                        ]
                    ]
                ]
            ],
            'Test.Type2' => [
                'translations' => [],
                'fields' => []
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
                    ->relation('other_type', [T('Test.Type2'), T('Test.Type3')], HasOneRelation::class)
                    ->relation('other_types', Type::list([T('Test.Type2'), T('Test.Type3')]), HasOneRelation::class);
            }
        );

        $schema = $api->toSchemaJson();

        $expectedTypesSchema = [
            'Test.Type' => [
                'translations' => [],
                'fields' => [
                    'other_type' => [
                        'type' => 'Afeefa.HasOneRelation',
                        'related_type' => [
                            'types' => ['Test.Type2', 'Test.Type3']
                        ]
                    ],
                    'other_types' => [
                        'type' => 'Afeefa.HasOneRelation',
                        'related_type' => [
                            'types' => ['Test.Type2', 'Test.Type3'],
                            'list' => true
                        ]
                    ]
                ]
            ],
            'Test.Type2' => [
                'translations' => [],
                'fields' => []
            ],
            'Test.Type3' => [
                'translations' => [],
                'fields' => []
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);
    }
}
