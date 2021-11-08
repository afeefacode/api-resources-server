<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\DI\Container;

class ActionBuilder
{
    private ?string $name = null;
    private bool $response = false;
    private bool $resolver = false;

    public function action(?string $name = null): ActionBuilder
    {
        $this->name = $name;
        return $this;
    }

    public function withResponse($response = true): ActionBuilder
    {
        $this->response = $response;
        return $this;
    }

    public function withResolver($resolver = true): ActionBuilder
    {
        $this->resolver = $resolver;
        return $this;
    }

    public function get(): Action
    {
        return $this->getAction(new Action());
    }

    public function createInContainer(): Action
    {
        $action = (new Container())->create(Action::class);
        return $this->getAction($action);
    }

    protected function getAction(Action $action)
    {
        if ($this->name) {
            $action->name($this->name);
        }

        if (!$action->hasResponse() && $this->response) {
            $action->response(T('Test.Type'));
        }

        if (!$action->hasResolver() && $this->resolver) {
            $action->resolve(function () {
            });
        }

        return $action;
    }
}
