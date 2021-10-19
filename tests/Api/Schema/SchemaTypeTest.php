<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Field\Fields\HasOneRelation;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Test\ApiBuilder;
use Afeefa\ApiResources\Test\TestApi;
use Afeefa\ApiResources\Test\TestResource;
use Afeefa\ApiResources\Test\TestType;
use Afeefa\ApiResources\Test\TypeBuilder;
use Afeefa\ApiResources\Type\Type;
use Closure;
use PHPUnit\Framework\TestCase;

class SchemaTypeTest extends TestCase
{
    public function test_simple()
    {
        $type = $this->createType('Test.Type', function (TestType $type) {
            $type
                ->attribute('title', VarcharAttribute::class)
                ->attribute('name', VarcharAttribute::class)
                ->relation('related_type', get_class($type), HasOneRelation::class);
        });

        $api = $this->createApi($type);

        // debug_dump($api->toSchemaJson());

        $schema = $api->toSchemaJson();

        $this->assertIsArray($schema['types']['Test.Type']);

        $typeSchema = $schema['types']['Test.Type'];
        $fieldsSchema = $typeSchema['fields'];

        $this->assertEquals(['title', 'name', 'related_type'], array_keys($fieldsSchema));

        $this->assertEquals(VarcharAttribute::$type, $fieldsSchema['name']['type']);
        $this->assertEquals(VarcharAttribute::$type, $fieldsSchema['title']['type']);
        $this->assertEquals(HasOneRelation::$type, $fieldsSchema['related_type']['type']);
        $this->assertEquals('Test.Type', $fieldsSchema['related_type']['related_type']);

        $this->assertEquals([], $schema['validators']);
        $this->assertArrayNotHasKey('update_fields', $typeSchema);
        $this->assertArrayNotHasKey('created_fields', $typeSchema);
    }

    private function createType(string $type, Closure $callback): Type
    {
        return (new TypeBuilder())
            ->type($type, function (Type $type) use ($callback) {
                $callback($type, $type->getFields());
            })
            ->get();
    }

    private function createApi(Type $type): Api
    {
        return (new ApiBuilder())
            ->api('Test.Api', function (TestApi $api) use ($type) {
                $api->resource('Test.Resource', function (TestResource $resource) use ($type) {
                    $resource->action('test_action', function (Action $action) use ($type) {
                        $action->response(get_class($type));
                    });
                });
            })
            ->get();
    }
}
