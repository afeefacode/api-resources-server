<?php

namespace Afeefa\ApiResources\Action;

use Afeefa\ApiResources\Bag\Bag;
use Afeefa\ApiResources\Bag\BagEntryInterface;
use Closure;

/**
 * @method Action get(string $name, Closure $callback)
 * @method Action[] getEntries()
 */
class ActionBag extends Bag
{
    public function action(string $name, Closure $callback): ActionBag
    {
        $this->setDefinition($name, $callback, function (Action $action) use ($name) {
            $action->name($name);
        });

        return $this;
    }

    public function query(string $name, $TypeClassOrClassesOrMeta, Closure $callback): ActionBag
    {
        $this->setDefinition($name, $callback, function (Action $action) use ($name, $TypeClassOrClassesOrMeta) {
            $action
                ->name($name)
                ->response($TypeClassOrClassesOrMeta);
        });

        return $this;
    }

    public function mutation(string $name, $TypeClassOrClassesOrMeta, Closure $callback): ActionBag
    {
        $this->setDefinition($name, $callback, function (Action $action) use ($name, $TypeClassOrClassesOrMeta) {
            $action
                ->name($name)
                ->input($TypeClassOrClassesOrMeta);
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

    /**
     * allow empty actions (without params etc) in schema
     */
    public function toSchemaJson(): array
    {
        return array_map(function (Action $action) {
            return $action->toSchemaJson();
        }, $this->getEntries());
    }
}
