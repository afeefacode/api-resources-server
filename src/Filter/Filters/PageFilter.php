<?php

namespace Afeefa\ApiResources\Filter\Filters;

use Afeefa\ApiResources\Filter\Filter;

class PageFilter extends Filter
{
    public static string $type = 'Afeefa.PageFilter';

    protected function setup(): void
    {
        $this->default(1);
    }
}
