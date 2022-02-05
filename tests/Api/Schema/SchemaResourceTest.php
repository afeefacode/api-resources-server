<?php

namespace Afeefa\ApiResources\Tests\Api\Schema;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Test\ApiBuilder;
use Afeefa\ApiResources\Test\ApiResourcesTest;

use function Afeefa\ApiResources\Test\createApiWithSingleResource;

use Afeefa\ApiResources\Test\ResourceBuilder;
use function Afeefa\ApiResources\Test\T;

use Closure;

class SchemaResourceTest extends ApiResourcesTest
{
    public function test_simple()
    {
        $api = createApiWithSingleResource(function (Closure $addAction) {
            $addAction('test_action', T('Test.Type'), function (Action $action) {
                $action
                    ->resolve(function () {
                    });
            });
            $addAction('test_action2', T('Test.Type2'), function (Action $action) {
                $action
                    ->resolve(function () {
                    });
            });
        });

        $schema = $api->toSchemaJson();

        $expectedResourcesSchema = [
            'Test.Resource' => [
                'test_action' => [
                    'response' => [
                        'type' => 'Test.Type'
                    ]
                ],
                'test_action2' => [
                    'response' => [
                        'type' => 'Test.Type2'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedResourcesSchema, $schema['resources']);
    }

    public function test_get_type_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestResource@anonymous/');

        $resource = (new ResourceBuilder())->resource()->get();

        $resource::type();
    }

    public function test_add_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestResource@anonymous/');

        (new ApiBuilder())
            ->api('Test.Api', function (Closure $addResource) {
                $addResource();
            })
            ->get();
    }
}
