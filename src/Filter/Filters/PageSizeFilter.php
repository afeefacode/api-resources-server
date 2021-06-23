<?php

namespace Afeefa\ApiResources\Filter\Filters;

use Afeefa\ApiResources\Filter\Filter;

class PageSizeFilter extends Filter
{
    public static string $type = 'Afeefa.PageSizeFilter';

    public function pageSizes(array $pageSizes): PageSizeFilter
    {
        return parent::options($pageSizes);
    }

    public function hasPageSize(int $pageSize = null): bool
    {
        $options = parent::getOptions();
        return in_array($pageSize, $options);
    }

    protected function setup(): void
    {
        $this
            ->options([15])
            ->default(15);
    }
}
