<?php

namespace Afeefa\ApiResources\TestsV2;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\TestV2\V2TestCase;
use Afeefa\ApiResources\V2\FieldBag as V2FieldBag;
use Afeefa\ApiResources\Validator\Validators\LinkOneValidator;
use Afeefa\ApiResources\Validator\Validators\StringValidator;

use function Afeefa\ApiResources\Test\T;

// Minimal test Api with a static type for ApiRequest::toSchemaJson()
class TestApi extends Api
{
    protected static string $type = 'Test.Api';
}

class V2SchemaCompatibilityTest extends V2TestCase
{
    public function test_schema_simple_attributes()
    {
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields
                    ->string('title')
                    ->string('name');
            }
        )->get();

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->string('title')->write(false)
                    ->string('name')->write(false);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_mutation_fields()
    {
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

        // V2: Default-Mitgliedschaft in allen drei Bags.
        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields->string('title');
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_validators()
    {
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

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->string('title')->write(validate: function (StringValidator $v) {
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
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->hasOne('author', T('Test.Author'));
            }
        )->get();

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasOne('author', T('Test.Author'))->write(false);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_link_relation()
    {
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

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasOne('category', T('Test.Category'))->write(mode: ['link']);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_save_relation_mode_create()
    {
        // mode: ['create'] entspricht v1 hasOne in updateFields/createFields (kein Link).
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->hasOne('address', T('Test.Address'));
            },
            function (FieldBag $updateFields) {
                $updateFields->hasOne('address', T('Test.Address'));
            },
            function (FieldBag $createFields, FieldBag $updateFields) {
                $createFields->from($updateFields, 'address');
            }
        )->get();

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasOne('address', T('Test.Address'))->write(mode: ['create']);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_link_or_create_relation_is_link_in_schema()
    {
        // mode: ['link', 'create'] enthaelt 'link' → Schema-seitig wie linkOne.
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->hasOne('customer', T('Test.Customer'));
            },
            function (FieldBag $updateFields) {
                $updateFields->linkOne('customer', T('Test.Customer'));
            },
            function (FieldBag $createFields, FieldBag $updateFields) {
                $createFields->from($updateFields, 'customer');
            }
        )->get();

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasOne('customer', T('Test.Customer'))->write(mode: ['link', 'create']);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_update_only_mode_is_not_link()
    {
        // mode: ['update'] enthaelt kein 'link' → Schema-seitig hasOne (kein Link-Flag).
        // Im CREATE-Bag ist die Relation nicht erlaubt → wir schliessen sie aus.
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->hasOne('settings', T('Test.Settings'));
            },
            function (FieldBag $updateFields) {
                $updateFields->hasOne('settings', T('Test.Settings'));
            }
        )->get();

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasOne('settings', T('Test.Settings'))->update(mode: ['update'])->create(false);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_link_or_update_mode_on_update_only()
    {
        // mode: ['link', 'update'] auf UPDATE → linkOne im UPDATE-Bag; nichts in CREATE.
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->hasOne('lieferadresse', T('Test.Address'));
            },
            function (FieldBag $updateFields) {
                $updateFields->linkOne('lieferadresse', T('Test.Address'));
            }
        )->get();

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasOne('lieferadresse', T('Test.Address'))->update(mode: ['link', 'update'])->create(false);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_polymorphic_relation()
    {
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->hasOne('owner', [T('Test.Company'), T('Test.Contact')]);
            },
            function (FieldBag $updateFields) {
                $updateFields->linkOne('owner', [T('Test.Company'), T('Test.Contact')]);
            },
            function (FieldBag $createFields, FieldBag $updateFields) {
                $createFields->from($updateFields, 'owner');
            }
        )->get();

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasOne('owner', [T('Test.Company'), T('Test.Contact')])
                        ->write(mode: ['link']);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_options_request_on_non_link_relation()
    {
        $testApi = $this->container->get(TestApi::class);
        $this->container->registerAlias($testApi, Api::class);

        $optionsCallback = function (ApiRequest $request) {
            $request
                ->resourceType('Test.AddressResource')
                ->actionName('list')
                ->fields(['city' => true]);
        };

        // Inline-hasOne (kein Link) mit optionsRequest.
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) use ($optionsCallback) {
                $fields->hasOne('address', T('Test.Address'), function (Relation $r) use ($optionsCallback) {
                    $r->optionsRequest($optionsCallback);
                });
            }
        )->get();

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) use ($optionsCallback) {
                $fields
                    ->hasOne('address', T('Test.Address'))->write(false)
                        ->optionsRequest($optionsCallback);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_required_validate_and_mode_combined()
    {
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->hasOne('gender', T('Test.Category'));
            },
            function (FieldBag $updateFields) {
                $updateFields->linkOne('gender', T('Test.Category'), function (Relation $r) {
                    $r->validate(fn (LinkOneValidator $v) => $v->filled())->required();
                });
            },
            function (FieldBag $createFields, FieldBag $updateFields) {
                $createFields->from($updateFields, 'gender');
            }
        )->get();

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasOne('gender', T('Test.Category'))
                        ->write(
                            mode: ['link'],
                            validate: fn (LinkOneValidator $v) => $v->filled(),
                            required: true,
                        );
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_with_has_many_relation()
    {
        $v1Type = $this->typeBuilder()->type(
            'Test.V1Type',
            function (FieldBag $fields) {
                $fields->hasMany('tags', T('Test.Tag'));
            }
        )->get();

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->hasMany('tags', T('Test.Tag'))->write(false);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }

    public function test_schema_mixed_operations()
    {
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

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->string('title')
                    ->string('created_at')->write(false)
                    ->date('date_start')->read(false)->update(false);
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

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) {
                $fields
                    ->string('name')->read(false)->update(false)->create(required: true);
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
        $testApi = $this->container->get(TestApi::class);
        $this->container->registerAlias($testApi, Api::class);

        $optionsCallback = function (ApiRequest $request) {
            $request
                ->resourceType('Test.CategoryResource')
                ->actionName('list')
                ->fields(['title' => true]);
        };

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

        $v2Type = $this->v2TypeBuilder()->type(
            'Test.V2Type',
            function (V2FieldBag $fields) use ($optionsCallback) {
                $fields
                    ->hasOne('category', T('Test.Category'))
                        ->write(mode: ['link'])
                        ->optionsRequest($optionsCallback);
            }
        )->get();

        $this->assertEquals(
            $v1Type->toSchemaJson(),
            $v2Type->toSchemaJson()
        );
    }
}
