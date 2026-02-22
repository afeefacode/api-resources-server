<?php

namespace Afeefa\ApiResources\TestsV2;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\TestV2\V2TestCase;
use Afeefa\ApiResources\V2\FieldBag as V2FieldBag;
use Afeefa\ApiResources\Validator\Validators\StringValidator;

use function Afeefa\ApiResources\Test\T;

// Minimal test Api with a static type for ApiRequest::toSchemaJson()
class TestApi extends Api
{
    protected static string $type = 'Test.Api';
}

use const Afeefa\ApiResources\V2\CREATE;
use const Afeefa\ApiResources\V2\READ;
use const Afeefa\ApiResources\V2\UPDATE;

class V2SchemaCompatibilityTest extends V2TestCase
{
    public function test_schema_simple_attributes()
    {
        // v1 type
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields
                    ->string('title')
                    ->string('name');
            }
        )->get();

        // v2 type
        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->string('title')->on(READ)
                    ->string('name')->on(READ);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_mutation_fields()
    {
        // v1 type with update and create fields
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->string('title');
            },
            function (FieldBag $updateFields) {
                $updateFields->string('title');
            },
            function (FieldBag $createFields, FieldBag $updateFields) {
                $createFields->from($updateFields, 'title');
            }
        )->get();

        // v2 equivalent
        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->string('title')->on(READ, UPDATE, CREATE);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_validators()
    {
        // v1 type
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->string('title');
            },
            function (FieldBag $updateFields) {
                $updateFields->string('title', validate: function (StringValidator $v) {
                    $v->filled()->min(2)->max(100);
                });
            },
            function (FieldBag $createFields, FieldBag $updateFields) {
                $createFields->from($updateFields, 'title');
            }
        )->get();

        // v2 equivalent
        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->string('title')->on(READ, UPDATE, CREATE)
                        ->onMutation(validate: function (StringValidator $v) {
                            $v->filled()->min(2)->max(100);
                        });
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_relations()
    {
        // v1 type
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->hasOne('author', T('Test.Author'));
            }
        )->get();

        // v2 type
        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasOne('author', T('Test.Author'))->on(READ);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_link_relation()
    {
        // v1 type: hasOne in fields, linkOne in updateFields
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->hasOne('category', T('Test.Category'));
            },
            function (FieldBag $updateFields) {
                $updateFields->linkOne('category', T('Test.Category'));
            },
            function (FieldBag $createFields, FieldBag $updateFields) {
                $createFields->from($updateFields, 'category');
            }
        )->get();

        // v2 equivalent with mode
        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasOne('category', T('Test.Category'))->on(READ, UPDATE, CREATE)
                        ->onMutation(mode: 'link');
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_has_many_relation()
    {
        // v1 type
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->hasMany('tags', T('Test.Tag'));
            }
        )->get();

        // v2 type
        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasMany('tags', T('Test.Tag'))->on(READ);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_mixed_operations()
    {
        // v1 type: different fields for read vs mutation
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields
                    ->string('title')
                    ->string('created_at');
            },
            function (FieldBag $updateFields) {
                $updateFields->string('title');
            },
            function (FieldBag $createFields, FieldBag $updateFields) {
                $createFields
                    ->from($updateFields, 'title')
                    ->date('date_start');
            }
        )->get();

        // v2 equivalent
        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->string('title')->on(READ, UPDATE, CREATE)
                    ->string('created_at')->on(READ)
                    ->date('date_start')->on(CREATE);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_required_field()
    {
        // v1 type: required on create
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            null,
            null,
            function (FieldBag $createFields) {
                $createFields->string('name', function ($a) {
                    $a->required();
                });
            }
        )->get();

        // v2 equivalent
        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->string('name')->on(CREATE)
                        ->onCreate(required: true);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_options_request()
    {
        // ApiRequest::toSchemaJson() needs an Api with a static $type.
        // Register a minimal test Api so the container can resolve Api::class.
        $testApi = $this->container->get(TestApi::class);
        $this->container->registerAlias($testApi, Api::class);

        $optionsCallback = function (ApiRequest $request) {
            $request
                ->resourceType('Test.CategoryResource')
                ->actionName('list')
                ->fields(['title' => true]);
        };

        // v1 type: all three field bags must set optionsRequest explicitly to match v2 behavior
        // (in v2, optionsRequest is global across all operations)
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) use ($optionsCallback) {
                $fields->hasOne('category', T('Test.Category'), function (Relation $r) use ($optionsCallback) {
                    $r->optionsRequest($optionsCallback);
                });
            },
            function (FieldBag $updateFields) use ($optionsCallback) {
                $updateFields->linkOne('category', T('Test.Category'), function (Relation $r) use ($optionsCallback) {
                    $r->optionsRequest($optionsCallback);
                });
            },
            function (FieldBag $createFields, FieldBag $updateFields) {
                $createFields->from($updateFields, 'category');
            }
        )->get();

        // v2 equivalent: single definition with ->on() + ->onMutation(mode:'link') + optionsRequest
        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) use ($optionsCallback) {
                $fields
                    ->hasOne('category', T('Test.Category'))->on(READ, UPDATE, CREATE)
                        ->onMutation(mode: 'link')
                        ->optionsRequest($optionsCallback);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }
}
