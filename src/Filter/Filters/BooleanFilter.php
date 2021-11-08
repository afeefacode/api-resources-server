<?php

namespace Afeefa\ApiResources\Filter\Filters;

use Afeefa\ApiResources\Filter\Filter;

class BooleanFilter extends Filter
{
    protected static string $type = 'Afeefa.BooleanFilter';

    public function values(array $values): BooleanFilter
    {
        return parent::options($values);
    }

    protected function setup(): void
    {
        $this->options([true]);
    }
}
