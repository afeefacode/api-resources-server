<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Bag\BagEntryInterface;
use Afeefa\ApiResources\DI\Container;
use function Afeefa\ApiResources\DI\factory;

use Closure;

class TestContainer extends Container
{
    public function __construct()
    {
        parent::__construct([
            ActionBag::class => factory(function () {
                $service = new TestActionBag();
                return $service;
            })
        ]);
    }
}

class TestActionBag extends ActionBag
{
    public function get(string $name, Closure $callback = null): BagEntryInterface
    {
        $action = parent::get($name, $callback);
        if (!$action->hasResolver()) {
            $action->resolve(function () {
            });
        }
        if (!$action->hasResponse()) {
            $action->response(T('Test.Type'));
        }
        return $action;
    }
}
