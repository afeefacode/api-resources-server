<?php

namespace Afeefa\ApiResources\DI;

use Closure;

class FactoryDefinition
{
    protected Closure $factory;

    public function __construct(Closure $factory)
    {
        $this->factory = $factory;
    }

    public function __invoke()
    {
        $factory = $this->factory;
        return $factory();
    }
}
