<?php

namespace Afeefa\ApiResources\Tests\Api\Schema;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionParams;
use Afeefa\ApiResources\Field\Fields\IdAttribute;
use Afeefa\ApiResources\Filter\FilterBag;
use Afeefa\ApiResources\Filter\Filters\KeywordFilter;
use Afeefa\ApiResources\Test\ApiResourcesTest;

use function Afeefa\ApiResources\Test\createApiWithSingleResource;

use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Type\Type;
use Closure;

class SchemaActionTest extends ApiResourcesTest
{
    public function test_simple_mutation()
    {
        $api = createApiWithSingleResource(function (Closure $addAction, Closure $addQuery, Closure $addMutation) {
            $addMutation('test_action', T('Test.InputType'), function (Action $action) {
                $action
                    ->params(function (ActionParams $params) {
                        $params->attribute('id', IdAttribute::class);
                    })
                    ->response(T('Test.ResponseType'))
                    ->filters(function (FilterBag $filters) {
                        $filters->add('search', KeywordFilter::class);
                    })
                    ->resolve(function () {
                    });
            });
        });

        $schema = $api->toSchemaJson();

        $expectedResourcesSchema = [
            'Test.Resource' => [
                'test_action' => [
                    'params' => [
                        'id' => [
                            'type' => 'Afeefa.IdAttribute'
                        ]
                    ],
                    'input' => [
                        'type' => 'Test.InputType'
                    ],
                    'filters' => [
                        'search' => [
                            'type' => 'Afeefa.KeywordFilter'
                        ]
                    ],
                    'response' => [
                        'type' => 'Test.ResponseType'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedResourcesSchema, $schema['resources']);
    }

    public function test_list_response()
    {
        $api = createApiWithSingleResource(function (Closure $addAction, Closure $addQuery) {
            $addQuery('test_action', Type::list(T('Test.ResponseType')), function (Action $action) {
                $action->resolve(function () {
                });
            });
        });

        $schema = $api->toSchemaJson();

        $expectedResourcesSchema = [
            'Test.Resource' => [
                'test_action' => [
                    'response' => [
                        'type' => 'Test.ResponseType',
                        'list' => true
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedResourcesSchema, $schema['resources']);
    }
}
