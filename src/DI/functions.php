<?php

namespace Afeefa\ApiResources\DI;

use Closure;

function factory(Closure $factory)
{
    return new FactoryDefinition($factory);
}

function create()
{
    return new CreateDefinition();
}
