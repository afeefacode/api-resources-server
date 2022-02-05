<?php

namespace Afeefa\ApiResources\Tests\Api\Schema;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Filter\Filter;
use Afeefa\ApiResources\Filter\FilterBag;
use Afeefa\ApiResources\Test\ApiResourcesTest;

use function Afeefa\ApiResources\Test\createApiWithSingleResource;
use Afeefa\ApiResources\Test\FilterBuilder;
use function Afeefa\ApiResources\Test\T;

use Closure;

class SchemaFilterTest extends ApiResourcesTest
{
    public function test_simple()
    {
        $api = $this->createApiWithFilter('check', function (Filter $filter) {
            $filter
                ->options([true, false])
                ->default('default')
                ->nullIsOption(true);
        });

        $schema = $api->toSchemaJson();

        $expectedResourcesSchema = [
            'Test.Resource' => [
                'test_action' => [
                    'filters' => [
                        'check' => [
                            'type' => 'Test.Filter',
                            'options' => [true, false],
                            'default' => 'default',
                            'null_is_option' => true
                        ]
                    ],
                    'response' => [
                        'type' => 'Test.Type'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedResourcesSchema, $schema['resources']);
    }

    public function test_implicitly_null_is_option()
    {
        $api = $this->createApiWithFilter('check', function (Filter $filter) {
            $filter
                ->options([null, true, false]);
        });

        $schema = $api->toSchemaJson();

        $expectedResourcesSchema = [
            'Test.Resource' => [
                'test_action' => [
                    'filters' => [
                        'check' => [
                            'type' => 'Test.Filter',
                            'options' => [null, true, false],
                            'null_is_option' => true
                        ]
                    ],
                    'response' => [
                        'type' => 'Test.Type'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedResourcesSchema, $schema['resources']);
    }

    public function test_options_request()
    {
        // auto save type with field 'name' into registry
        $this->typeBuilder()->type('Test.Type', function (FieldBag $fields) {
            $fields->attribute('name', StringAttribute::class);
        })->get();

        $api = $this->createApiWithFilter('check', function (Filter $filter) {
            $filter
                ->optionsRequest(function (ApiRequest $request) {
                    $request
                        ->resourceType('Test.Resource')
                        ->actionName('test_action')
                        ->fields(['name' => true]);
                });
        });

        $schema = $api->toSchemaJson();

        $expectedResourcesSchema = [
            'Test.Resource' => [
                'test_action' => [
                    'filters' => [
                        'check' => [
                            'type' => 'Test.Filter',
                            'options_request' => [
                                'api' => 'Test.Api',
                                'resource' => 'Test.Resource',
                                'action' => 'test_action',
                                'fields' => [
                                    'name' => true
                                ]
                            ]
                        ]
                    ],
                    'response' => [
                        'type' => 'Test.Type'
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedResourcesSchema, $schema['resources']);
    }

    public function test_add_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestFilter@anonymous/');

        $filter = (new FilterBuilder())->filter()->get();

        $api = createApiWithSingleResource(function (Closure $addAction) use ($filter) {
            $addAction('test_action', T('Test.Type'), function (Action $action) use ($filter) {
                $action
                    ->filters(function (FilterBag $filters) use ($filter) {
                        $filters->add('test_filter', $filter::class);
                    })
                    ->resolve(function () {
                    });
            });
        });

        $api->toSchemaJson();
    }

    private function createApiWithFilter($name, Closure $filterCallback)
    {
        $filter = (new FilterBuilder())->filter('Test.Filter')->get();

        return createApiWithSingleResource(function (Closure $addAction) use ($name, $filter, $filterCallback) {
            $addAction('test_action', T('Test.Type'), function (Action $action) use ($name, $filter, $filterCallback) {
                $action
                    ->filters(function (FilterBag $filters) use ($name, $filter, $filterCallback) {
                        $filters->add($name, $filter::class);
                        $filterCallback($filters->get($name));
                    })
                    ->resolve(function () {
                    });
            });
        });
    }
}
