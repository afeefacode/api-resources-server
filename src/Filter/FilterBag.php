<?php

namespace Afeefa\ApiResources\Filter;

use Afeefa\ApiResources\Bag\Bag;

/**
 * @method Filter get(string $name)
 * @method Filter[] entries()
 */
class FilterBag extends Bag
{
    public function add(string $name, $classOrCallback): FilterBag
    {
        $this->container->create($classOrCallback, function (Filter $filter) use ($name) {
            $filter->name($name);
            $this->set($name, $filter);
        });

        return $this;
    }
}
