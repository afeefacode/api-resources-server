<?php

namespace Afeefa\ApiResources\Tests\Api\Schema;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Test\ApiBuilder;
use Afeefa\ApiResources\Test\ApiResourcesTest;

use function Afeefa\ApiResources\Test\createApiWithSingleResource;

use function Afeefa\ApiResources\Test\T;
use Afeefa\ApiResources\Validator\Validators\StringValidator;
use Closure;

class SchemaApiTest extends ApiResourcesTest
{
    public function test_simple()
    {
        $api = $this->createApiWithType(
            'Test.Type',
            function (FieldBag $fields) {
                $fields
                    ->attribute('title', function (StringAttribute $attribute) {
                        $attribute->validate(function (StringValidator $v) {
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
                    'fields' => [
                        'title' => [
                            'type' => 'Afeefa.StringAttribute',
                            'validator' => [
                                'type' => 'Afeefa.StringValidator',
                                'params' => [
                                    'filled' => true,
                                    'min' => 2,
                                    'max' => 100
                                ]
                            ]
                        ]
                    ],
                    'update_fields' => [],
                    'create_fields' => []
                ]
            ],
            'validators' => [
                'Afeefa.StringValidator' => [
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

        $api::type();
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
        ?Closure $addActionCallback = null
    ): Api {
        $this->typeBuilder()->type($typeName, $fieldsCallback)->get();

        if (!$addActionCallback) {
            $addActionCallback = function (Closure $addAction) use ($typeName) {
                $addAction('test_action', T($typeName), function (Action $action) use ($typeName) {
                    $action
                        ->resolve(function () {
                        });
                });
            };
        }

        return createApiWithSingleResource($addActionCallback);
    }
}
