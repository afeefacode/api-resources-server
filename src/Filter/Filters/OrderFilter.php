<?php

namespace Afeefa\ApiResources\Filter\Filters;

use Afeefa\ApiResources\Filter\Filter;

class OrderFilter extends Filter
{
    protected static string $type = 'Afeefa.OrderFilter';

    public const DESC = 'desc';

    public const ASC = 'asc';

    public function fields(array $fields): OrderFilter
    {
        return parent::options($fields);
    }

    public function field($field, $directions): OrderFilter
    {
        $this->options[$field] = $directions;
        return $this;
    }

    public function hasField(array $field): bool
    {
        $field = array_keys($field)[0] ?? null;
        foreach (array_keys($this->options) as $existingField) {
            if ($field === $existingField) {
                return true;
            }
        }
        return false;
    }
}
