<?php

namespace Afeefa\ApiResources\DI;

trait ContainerAwareTrait
{
    protected Container $container;

    public function container(Container $container): void
    {
        $this->container = $container;
    }

    public function created(): void
    {
    }
}
