<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\HasOneRelation;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Resource\ResourceBag;
use Afeefa\ApiResources\Test\ApiBuilder;
use Afeefa\ApiResources\Test\ResourceBuilder;
use function Afeefa\ApiResources\Test\T;
use Afeefa\ApiResources\Test\TypeBuilder;
use Afeefa\ApiResources\Test\TypeRegistry;
use Afeefa\ApiResources\Validator\Validators\VarcharValidator;
use Closure;

use PHPUnit\Framework\TestCase;

class SchemaTypeTest extends TestCase
{
    protected function setUp(): void
    {
        TypeRegistry::reset();
    }

    public function test_simple()
    {
        $api = $this->createApiWithType(
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
                        'related_type' => 'Test.Type'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedTypesSchema, $schema['types']);

        $this->assertEquals([], $schema['validators']);
    }

    public function test_validator()
    {
        $api = $this->createApiWithType(
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
        $api = $this->createApiWithType(
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
        $api = $this->createApiWithType(
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
        (new TypeBuilder())->type('Test.Type');

        $api = $this->createApi();

        $schema = $api->toSchemaJson();

        $this->assertEquals([], array_keys($schema['types']));
    }

    public function test_type_in_action_input()
    {
        (new TypeBuilder())->type('Test.Type');

        $api = $this->createApi(
            actionsCallback: function (ActionBag $actions) {
                $actions->add('type', function (Action $action) {
                    $action->input(T('Test.Type'));
                });
            }
        );

        $schema = $api->toSchemaJson();

        $this->assertEquals(['Test.Type'], array_keys($schema['types']));
    }

    public function test_type_in_action_response()
    {
        (new TypeBuilder())->type('Test.Type');

        $api = $this->createApi(
            actionsCallback: function (ActionBag $actions) {
                $actions->add('type', function (Action $action) {
                    $action->response(T('Test.Type'));
                });
            }
        );

        $schema = $api->toSchemaJson();

        $this->assertEquals(['Test.Type'], array_keys($schema['types']));
    }

    public function test_type_in_relation()
    {
        (new TypeBuilder())->type('Test.Type2');

        $api = $this->createApiWithType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->relation('other_type', T('Test.Type2'), HasOneRelation::class);
            }
        );

        $schema = $api->toSchemaJson();

        $this->assertEquals(['Test.Type', 'Test.Type2'], array_keys($schema['types']));
    }

    private function createApiWithType(
        string $typeName,
        ?Closure $fieldsCallback = null,
        ?Closure $actionsCallback = null
    ): Api {
        (new TypeBuilder())->type($typeName, $fieldsCallback);

        if (!$actionsCallback) {
            $actionsCallback = function (ActionBag $actions) use ($typeName) {
                $actions->add('test_action', function (Action $action) use ($typeName) {
                    $action->response(T($typeName));
                });
            };
        }

        return $this->createApi($actionsCallback);
    }

    private function createApi(?Closure $actionsCallback = null): Api
    {
        $resource = (new ResourceBuilder())
            ->resource('Test.Resource', $actionsCallback)
            ->get();

        return (new ApiBuilder())
            ->api(
                'Test.Api',
                function (ResourceBag $resources) use ($resource) {
                    $resources->add($resource::class);
                }
            )
            ->get();
    }
}
