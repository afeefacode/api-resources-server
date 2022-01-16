<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\DI\Container;
use PHPUnit\Framework\TestCase;

class ApiResourcesTest extends TestCase
{
    public static Container $staticContainer;
    protected Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        static::$staticContainer = $this->container;
    }

    protected function apiBuilder(): ApiBuilder
    {
        return (new ApiBuilder($this->container));
    }

    protected function resourceBuilder(): ResourceBuilder
    {
        return (new ResourceBuilder($this->container));
    }

    protected function typeBuilder(): TypeBuilder
    {
        return (new TypeBuilder($this->container));
    }

    protected function fieldBuilder(): FieldBuilder
    {
        return (new FieldBuilder($this->container));
    }
}
