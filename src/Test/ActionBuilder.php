<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Action\Action;
use Closure;

class ActionBuilder extends Builder
{
    private ?string $name = null;
    private ?Closure $actionCallback = null;

    public function action(?string $name = null, ?Closure $actionCallback = null): ActionBuilder
    {
        $this->name = $name;
        $this->actionCallback = $actionCallback;
        return $this;
    }

    public function get(): Action
    {
        $action = $this->container->create(Action::class);
        if ($this->name) {
            $action->name($this->name);
        }

        if ($this->actionCallback) {
            ($this->actionCallback)($action);
        }
        return $action;
    }
}
