<?php

namespace Afeefa\ApiResources\Resource;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Api\Authorizator;
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

    /**
     * A locked action is left out.
     *
     * The schema says what a client may ask for, and an action that answers
     * every call with a NotFoundException is not that. Leaving it in means a
     * button in the interface that can only fail. The api is built per request,
     * after the sign-in, so the schema is allowed to differ per role.
     */
    public function toSchemaJson(): array
    {
        $authorizator = $this->container->get(Authorizator::class);

        return array_filter(
            $this->actions->toSchemaJson(),
            fn (string $name) => !$authorizator->isActionForbidden($this::type(), $name),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected function actions(ActionBag $actions): void
    {
    }
}
