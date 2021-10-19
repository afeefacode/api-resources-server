<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Resource\Resource;
use Closure;

class ApiBuilder
{
    public Container $container;
    public TestApi $api;

    public function api(string $type, Closure $callback): ApiBuilder
    {
        $this->container = new Container();

        $api = new class() extends TestApi {};
        get_class($api)::$type = $type;

        $this->api = $this->container->create(get_class($api));
        $callback($this->api);

        return $this;
    }

    public function get(): Api
    {
        return $this->api;
    }
}

class TestApi extends Api
{
    public function resource(string $type, Closure $callback): TestApi
    {
        $resource = new class() extends TestResource {};
        $ResourceClass = get_class($resource);
        $ResourceClass::$type = $type;

        $this->resources->add($ResourceClass);
        $newResource = $this->resources->get($type);
        $callback($newResource);

        return $this;
    }
}

class TestResource extends Resource
{
    public function action(string $name, Closure $callback): TestResource
    {
        $this->actions->add($name, $callback);

        return $this;
    }
}
