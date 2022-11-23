<?php

namespace Afeefa\ApiResources;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\DI\Container;

class ApiResources
{
    protected static ?Container $container = null;

    public function __construct()
    {
        static::$container = null; // reset container
    }

    public function getApi(string $ApiClass): Api
    {
        $container = $this->getContainer();
        return $container->get($ApiClass);
    }

    public function requestFromInput($ApiClass, ?array $input = null): array
    {
        $container = $this->getContainer();
        /** @var Api */
        $api = $container->get($ApiClass);
        return $api->requestFromInput($input);
    }

    public function toSchemaJson($ApiClass)
    {
        $container = $this->getContainer();
        $api = $container->get($ApiClass);
        return $api->toSchemaJson();
    }

    protected function getContainer(): Container
    {
        if (!self::$container) {
            self::$container = new Container();
        }
        return self::$container;
    }
}
