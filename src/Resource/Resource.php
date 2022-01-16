<?php

namespace Afeefa\ApiResources\Resource;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\Utils\HasStaticTypeTrait;

class Resource extends BagEntry
{
    use HasStaticTypeTrait;

    protected ActionBag $actions;

    public function created(): void
    {
        $this->actions = $this->container->create(ActionBag::class);
        $this->actions($this->actions);
    }

    public function getAction(string $name): Action
    {
        return $this->actions->get($name);
    }

    public function getActions(): ActionBag
    {
        return $this->actions;
    }

    public function toSchemaJson(): array
    {
        return $this->actions->toSchemaJson();
    }

    protected function actions(ActionBag $actions): void
    {
    }
}
