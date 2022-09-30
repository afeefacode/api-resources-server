<?php

namespace Afeefa\ApiResources;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\DI\Container;

class ApiResources
{
    protected static Container $container;

    public function getApi(string $ApiClass): Api
    {
        $container = $this->getContainer();
        return $container->get($ApiClass);
    }

    public function requestFromInput($ApiClass)
    {
        $container = $this->getContainer();
        $api = $container->get($ApiClass);
        return $api->requestFromInput();
    }

    public function toSchemaJson($ApiClass)
    {
        $container = $this->getContainer();
        $api = $container->get($ApiClass);
        return $api->toSchemaJson();
    }

    protected function getContainer(): Container
    {
        if (!isset(self::$container)) {
            self::$container = new Container();
        }
        return self::$container;
    }
}
