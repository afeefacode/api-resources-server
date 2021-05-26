<?php

namespace Afeefa\ApiResources\Resource;

use Afeefa\ApiResources\Bag\Bag;

/**
 * @method Resource get(string $name)
 * @method Resource[] entries()
 */
class ResourceBag extends Bag
{
    public function add($classOrCallback): ResourceBag
    {
        $this->container->create($classOrCallback, function (Resource $resource) {
            $this->set($resource::$type, $resource);
        });

        return $this;
    }
}
