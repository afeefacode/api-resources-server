<?php

namespace Afeefa\ApiResources\Tests\Fixtures\TestApi;

use Afeefa\ApiResources\DB\ActionResolver;
use Afeefa\ApiResources\DB\ResolveContext;
use Afeefa\ApiResources\Model\Model;

class TestResolver
{
    public function get_types(ActionResolver $r)
    {
        $r
            ->load(function (ResolveContext $c) use ($r) {
                $request = $r->getRequest();
                $requestedFields = $request->getFields();
                $filters = $request->getFilters();

                $pageSizeFilter = $r->getAction()->getFilter('page_size');
                $pageSize = $filters['page_size'] ?? $pageSizeFilter->getDefaultValue();

                $fieldNames = $requestedFields->getFieldNames();
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

                return Model::fromList(TestType::$type, $objects);
            });
    }
}
