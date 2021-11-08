<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Test\ApiBuilder;
use function Afeefa\ApiResources\Test\createApiWithSingleResource;

use function Afeefa\ApiResources\Test\T;
use Afeefa\ApiResources\Test\TypeBuilder;
use Afeefa\ApiResources\Test\TypeRegistry;
use Afeefa\ApiResources\Validator\Validators\VarcharValidator;
use Closure;

use PHPUnit\Framework\TestCase;

class SchemaApiTest extends TestCase
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
                    ->attribute('title', function (VarcharAttribute $attribute) {
                        $attribute->validate(function (VarcharValidator $v) {
                            $v
                                ->filled()
                                ->min(2)
                                ->max(100);
                        });
                    });
            }
        );

        $schema = $api->toSchemaJson();

        $expectedApiSchema = [
            'type' => 'Test.Api',
            'resources' => [
                'Test.Resource' => [
                    'test_action' => [
                        'response' => [
                            'type' => 'Test.Type'
                        ]
                    ]
                ]
            ],
            'types' => [
                'Test.Type' => [
                    'translations' => [],
                    'fields' => [
                        'title' => [
                            'type' => 'Afeefa.VarcharAttribute',
                            'validator' => [
                                'type' => 'Afeefa.VarcharValidator',
                                'params' => [
                                    'filled' => true,
                                    'min' => 2,
                                    'max' => 100
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'validators' => [
                'Afeefa.VarcharValidator' => [
                    'rules' => [
                        'string' => [
                            'message' => '{{ fieldLabel }} sollte eine Zeichenkette sein.'
                        ],
                        'null' => [
                            'message' => '{{ fieldLabel }} sollte eine Zeichenkette sein.'
                        ],
                        'min' => [
                            'message' => '{{ fieldLabel }} sollte mindestens {{ param }} Zeichen beinhalten.'
                        ],
                        'max' => [
                            'message' => '{{ fieldLabel }} sollte maximal {{ param }} Zeichen beinhalten.'
                        ],
                        'filled' => [
                            'message' => '{{ fieldLabel }} sollte einen Wert enthalten.'
                        ],
                        'regex' => [
                            'message' => '{{ fieldLabel }} sollte ein gültiger Wert sein.'
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedApiSchema, $schema);
    }

    public function test_get_type_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestApi@anonymous/');

        $api = (new ApiBuilder())->api()->get();

        $api->type();
    }

    public function test_schema_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestApi@anonymous/');

        $api = (new ApiBuilder())->api()->get();

        $api->toSchemaJson();
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

        return createApiWithSingleResource($actionsCallback);
    }
}
