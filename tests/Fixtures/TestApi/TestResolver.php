<?php

namespace Afeefa\ApiResources\Tests\Fixtures\TestApi;

use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\QueryActionResolver;

class TestResolver
{
    public function get_types(QueryActionResolver $r)
    {
        $r
            ->load(function () use ($r) {
                $request = $r->getRequest();
                $fieldNames = $r->getRequestedFieldNames();
                $filters = $request->getFilters();

                $pageSizeFilter = $request->getAction()->getFilter('page_size');
                $pageSize = $filters['page_size'] ?? $pageSizeFilter->getDefaultValue();

                $objects = [];
                foreach (range(1, $pageSize) as $id) {
                    $object = [
                        'id' => $id
                    ];

                    foreach ($fieldNames as $name) {
                        $object[$name] = true;
                    }

                    $objects[] = $object;
                }

                return Model::fromList(TestType::type(), $objects);
            });
    }
}
