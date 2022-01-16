<?php

namespace Afeefa\ApiResources\Filter;

use Afeefa\ApiResources\Bag\Bag;
use Afeefa\ApiResources\Bag\BagEntryInterface;

/**
 * @method Filter get(string $name)
 * @method Filter[] getEntries()
 */
class FilterBag extends Bag
{
    public function add(string $name, $classOrCallback): FilterBag
    {
        $this->container->create($classOrCallback, function (Filter $filter) use ($name) {
            $filter->name($name);
            $this->setInternal($name, $filter);
        });

        return $this;
    }

    /**
     * disabled
     */
    public function set(string $name, BagEntryInterface $value): Bag
    {
        return $this;
    }
}
