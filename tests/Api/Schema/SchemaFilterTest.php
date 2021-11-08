<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Filter\Filter;
use Afeefa\ApiResources\Filter\FilterBag;
use function Afeefa\ApiResources\Test\createApiWithSingleResource;
use Afeefa\ApiResources\Test\FilterBuilder;

use function Afeefa\ApiResources\Test\T;
use Closure;
use PHPUnit\Framework\TestCase;

class SchemaFilterTest extends TestCase
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

    public function test_get_type_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestFilter@anonymous/');

        $filter = (new FilterBuilder())
            ->filter()
            ->get();

        $filter->type();
    }

    public function test_add_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestFilter@anonymous/');

        $filter = (new FilterBuilder())->filter()->get();

        $api = createApiWithSingleResource(function (ActionBag $actions) use ($filter) {
            $actions
                ->add('test_action', function (Action $action) use ($filter) {
                    $action
                        ->filters(function (FilterBag $filters) use ($filter) {
                            $filters->add('test_filter', $filter::class);
                        });
                });
        });

        $api->toSchemaJson();
    }

    private function createApiWithFilter($name, Closure $filterCallback)
    {
        $filter = (new FilterBuilder())->filter('Test.Filter')->get();

        return createApiWithSingleResource(function (ActionBag $actions) use ($name, $filter, $filterCallback) {
            $actions
                ->add('test_action', function (Action $action) use ($name, $filter, $filterCallback) {
                    $action
                        ->filters(function (FilterBag $filters) use ($name, $filter, $filterCallback) {
                            $filters->add($name, $filter::class);
                            $filterCallback($filters->get($name));
                        })
                        ->response(T('Test.Type'));
                });
        });
    }
}
