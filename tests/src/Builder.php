<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\DI\Container;

class Builder
{
    protected Container $container;

    public function __construct(?Container $container = null)
    {
        $this->container = $container ?? new Container();
    }
}
