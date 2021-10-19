<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Test\ApiBuilder;
use Afeefa\ApiResources\Test\TestApi;
use Afeefa\ApiResources\Test\TestResource;
use Afeefa\ApiResources\Test\TypeBuilder;
use Afeefa\ApiResources\Type\Type;
use Closure;
use PHPUnit\Framework\TestCase;

class SchemaResourceTest extends TestCase
{
    public function test_simple()
    {
        $type = $this->createType('Test.Type');

        $api = $this->createApi($type);

        // debug_dump($api->toSchemaJson());

        $schema = $api->toSchemaJson();

        $this->assertEquals(['Test.Resource'], array_keys($schema['resources']));

        $resourceSchema = $schema['resources']['Test.Resource'];

        $this->assertEquals(['test_action'], array_keys($resourceSchema));

        $actionSchema = $resourceSchema['test_action'];

        $this->assertEquals(['response' => [
            'type' => 'Test.Type'
        ]], $actionSchema);
    }

    private function createType(string $type, ?Closure $callback = null): Type
    {
        return (new TypeBuilder())
            ->type($type)
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
