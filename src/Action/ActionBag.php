<?php

namespace Afeefa\ApiResources\Action;

use Afeefa\ApiResources\Bag\Bag;
use Closure;

/**
 * @method Action get(string $name)
 * @method Action[] getEntries()
 */
class ActionBag extends Bag
{
    public function add(string $name, Closure $callback): ActionBag
    {
        $this->setDefinition($name, $callback, function (Action $action) use ($name) {
            $action->name($name);
        });

        return $this;
    }
}
