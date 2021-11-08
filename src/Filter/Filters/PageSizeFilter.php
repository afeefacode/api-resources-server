<?php

namespace Afeefa\ApiResources\Filter\Filters;

use Afeefa\ApiResources\Filter\Filter;

class PageSizeFilter extends Filter
{
    protected static string $type = 'Afeefa.PageSizeFilter';

    public function pageSizes(array $pageSizes): PageSizeFilter
    {
        return $this->options($pageSizes);
    }

    public function getPageSizes(): array
    {
        return $this->getOptions();
    }

    public function hasPageSize(?int $pageSize): bool
    {
        return $this->hasOption($pageSize);
    }

    protected function setup(): void
    {
        $this
            ->options([15])
            ->default(15);
    }
}
