<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Resource\ResourceBag;
use Afeefa\ApiResources\Test\ApiBuilder;
use Afeefa\ApiResources\Test\ResourceBuilder;
use function Afeefa\ApiResources\Test\T;
use Closure;

use PHPUnit\Framework\TestCase;

class SchemaResourceTest extends TestCase
{
    public function test_simple()
    {
        $api = $this->createApiWithResource(
            'Test.Resource',
            function (ActionBag $actions) {
                $actions
                    ->add('test_action', function (Action $action) {
                        $action->response(T('Test.Type'));
                    });
            }
        );

        $schema = $api->toSchemaJson();

        $expectedResourcesSchema = [
            'Test.Resource' => [
                'test_action' => [
                    'response' => [
                        'type' => 'Test.Type'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedResourcesSchema, $schema['resources']);
    }

    private function createApiWithResource(string $type, Closure $actionsCallback): Api
    {
        $resource = (new ResourceBuilder())
            ->resource($type, $actionsCallback)
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
