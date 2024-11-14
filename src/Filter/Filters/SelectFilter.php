<?php

namespace Afeefa\ApiResources\Filter\Filters;

use Afeefa\ApiResources\Filter\Filter;

class SelectFilter extends Filter
{
    protected static string $type = 'Afeefa.SelectFilter';

    private bool $multiple = false;

    public function multiple(): static
    {
        $this->multiple = true;
        return $this;
    }

    public function toSchemaJson(): array
    {
        $json = parent::toSchemaJson();

        if ($this->multiple) {
            $json['multiple'] = true;
        }

        return $json;
    }
}
