<?php

namespace Afeefa\ApiResources\Tests\Fixtures\TestApi;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Filter\FilterBag;
use Afeefa\ApiResources\Filter\Filters\PageSizeFilter;
use Afeefa\ApiResources\Resource\Resource;
use Afeefa\ApiResources\Type\Type;

class TestResource extends Resource
{
    protected static string $type = 'TestResource';

    protected function actions(ActionBag $actions): void
    {
        $actions->add('get_types', function (Action $action) {
            $action
                ->filters(function (FilterBag $filters) {
                    $filters->add('page_size', function (PageSizeFilter $filter) {
                        $filter
                            ->pageSizes([5, 15, 30, 50])
                            ->default(5);
                    });
                })

                ->response(Type::list(TestType::class))

                ->resolve([TestResolver::class, 'get_types']);
        });
    }
}
