<?php

namespace Afeefa\ApiResources\Filter\Filters;

use Afeefa\ApiResources\Filter\Filter;

class OrderFilter extends Filter
{
    public static string $type = 'Afeefa.OrderFilter';

    public const DESC = 'desc';

    public const ASC = 'asc';

    public function fields(array $fields): OrderFilter
    {
        return parent::options($fields);
    }
}
